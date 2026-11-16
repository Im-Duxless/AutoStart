<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\Payment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function balance(User $student): array
    {
        $groups = $student->approvedGroups()->get(['study_groups.id', 'tuition']);
        $totals = $student->payments()->whereIn('status', ['received', 'pending'])
            ->selectRaw('status, SUM(amount) as total')->groupBy('status')->pluck('total', 'status');
        $paid = BigDecimal::of((string) ($totals['received'] ?? '0'))->toScale(2, RoundingMode::HalfUp);
        $pending = BigDecimal::of((string) ($totals['pending'] ?? '0'))->toScale(2, RoundingMode::HalfUp);

        $tuition = null;
        $due = null;
        $available = null;
        if ($groups->isNotEmpty() && ! $groups->contains(fn ($group) => $group->tuition === null)) {
            $tuition = BigDecimal::of('0.00');
            foreach ($groups as $group) {
                $tuition = $tuition->plus($group->tuition);
            }
            $due = BigDecimal::max('0.00', $tuition->minus($paid));
            $available = BigDecimal::max('0.00', $due->minus($pending));
        }

        return [
            'paid' => (string) $paid,
            'tuition' => $tuition === null ? null : (string) $tuition,
            'due' => $due === null ? null : (string) $due,
            'pending' => (string) $pending,
            'available' => $available === null ? null : (string) $available,
        ];
    }

    public function checkout(int $studentId, array $data): Payment
    {
        return DB::transaction(function () use ($studentId, $data) {
            $student = $this->lockStudent($studentId);
            $reference = strtolower($data['reference']);
            $amount = BigDecimal::of((string) $data['amount']);
            $method = $data['payment_method'];

            $existing = $student->payments()->where('demo_reference', $reference)->first();
            if ($existing) {
                if ($existing->payment_method !== $method || ! $amount->isEqualTo($existing->amount)) {
                    throw new BusinessException('Эта операция уже была отправлена с другими данными.');
                }

                return $existing;
            }

            $balance = $this->balance($student);
            if ($balance['available'] === null) {
                throw new BusinessException('Взнос доступен после зачисления в группу с указанной стоимостью обучения.');
            }
            if ($amount->isGreaterThan($balance['available'])) {
                throw new BusinessException('Сумма превышает доступный остаток. Проверьте баланс и заявки на наличные.');
            }

            return $student->payments()->create([
                'amount' => (string) $amount,
                'payment_date' => today(),
                'payment_method' => $method,
                'status' => $method === 'card' ? 'received' : 'pending',
                'comment' => $method === 'card' ? 'Тестовая карта •••• 4242' : 'Учебный взнос наличными',
                'demo_reference' => $reference,
            ]);
        }, 5);
    }

    public function decideCashPayment(Payment $payment, string $status): Payment
    {
        return DB::transaction(function () use ($payment, $status) {
            $student = $this->lockStudent($payment->user_id);
            $current = $student->payments()->lockForUpdate()->findOrFail($payment->id);
            if ($current->demo_reference === null || $current->payment_method !== 'cash') {
                throw new BusinessException('Эта запись не является заявкой на оплату наличными.');
            }
            if ($current->status === $status) {
                return $current;
            }
            if ($current->status !== 'pending') {
                throw new BusinessException('По этой заявке уже принято решение.');
            }

            if ($status === 'received') {
                $due = $this->balance($student)['due'];
                if ($due === null || BigDecimal::of($current->amount)->isGreaterThan($due)) {
                    throw new BusinessException('Текущий остаток по обучению не позволяет подтвердить этот взнос.');
                }
                $current->payment_date = today();
            }
            $current->status = $status;
            $current->save();

            return $current;
        }, 5);
    }

    public function protectPendingCash(User $student, BigDecimal $additionalAmount): void
    {
        if (! $additionalAmount->isPositive() || ! $student->payments()->where('status', 'pending')->exists()) {
            return;
        }
        $available = $this->balance($student)['available'];
        if ($available === null || $additionalAmount->isGreaterThan($available)) {
            throw new BusinessException('Часть остатка уже зарезервирована. Обработайте заявку на наличные в журнале платежей.');
        }
    }

    public function lockStudent(int $id): User
    {
        $student = User::query()->lockForUpdate()->find($id);
        if ($student?->role !== UserRole::Student) {
            throw new BusinessException('Платёж можно учесть только для курсанта.');
        }

        return $student;
    }
}
