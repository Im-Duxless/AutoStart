<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = Payment::with('user')
            ->when($request->user()->role === UserRole::Student, fn (Builder $query) => $query->where('user_id', $request->user()->id));
        foreach (['user_id', 'status', 'payment_method'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->validated($field));
            }
        }
        $query->when($request->validated('from'), fn (Builder $query, $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($request->validated('to'), fn (Builder $query, $date) => $query->whereDate('payment_date', '<=', $date))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->validated('search');
                $query->where(fn (Builder $fields) => $fields->where('comment', 'like', "%$search%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%$search%")));
            });

        $received = (clone $query)->where('status', 'received')->sum('amount');

        return PaymentResource::collection($request->paginate($query, 'payment_date'))->additional([
            'received_total' => (string) BigDecimal::of((string) $received)->toScale(2, RoundingMode::HalfUp),
        ]);
    }

    public function show(Payment $payment): PaymentResource
    {
        Gate::authorize('view', $payment);

        return new PaymentResource($payment->load('user'));
    }

    public function store(PaymentRequest $request): PaymentResource
    {
        $payment = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $this->checkStudent($data['user_id']);

            return Payment::create($data)->refresh();
        }, 5);

        return new PaymentResource($payment->load('user'));
    }

    public function update(PaymentRequest $request, Payment $payment): PaymentResource
    {
        $updated = DB::transaction(function () use ($request, $payment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $data = $request->validated();
            $this->checkStudent($data['user_id'] ?? $payment->user_id);
            $payment->update($data);

            return $payment;
        }, 5);

        return new PaymentResource($updated->load('user'));
    }

    public function destroy(Payment $payment): Response
    {
        $payment->delete();

        return response()->noContent();
    }

    public function balance(Request $request): JsonResponse
    {
        $student = $request->user();
        $groups = $student->approvedGroups()->get(['study_groups.id', 'name', 'tuition']);
        $paid = BigDecimal::of((string) $student->payments()->where('status', 'received')->sum('amount'))->toScale(2, RoundingMode::HalfUp);
        $tuition = null;
        $due = null;
        if ($groups->isNotEmpty() && ! $groups->contains(fn ($group) => $group->tuition === null)) {
            $tuition = BigDecimal::of('0.00');
            foreach ($groups as $group) {
                $tuition = $tuition->plus($group->tuition);
            }
            $due = BigDecimal::max('0.00', $tuition->minus($paid));
        }

        return response()->json(['data' => [
            'paid' => (string) $paid,
            'tuition' => $tuition === null ? null : (string) $tuition,
            'due' => $due === null ? null : (string) $due,
        ]]);
    }

    private function checkStudent(int $id): void
    {
        $student = User::query()->lockForUpdate()->find($id);
        if ($student?->role !== UserRole::Student) {
            throw new BusinessException('Платёж можно учесть только для курсанта.');
        }
    }
}
