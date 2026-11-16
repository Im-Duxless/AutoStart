<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DemoPaymentRequest;
use App\Http\Requests\ListRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
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
    public function __construct(private PaymentService $payments) {}

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
            $student = $this->payments->lockStudent($data['user_id']);
            if (($data['status'] ?? 'received') === 'received') {
                $this->payments->protectPendingCash($student, BigDecimal::of((string) $data['amount']));
            }

            return Payment::create($data)->refresh();
        }, 5);

        return new PaymentResource($payment->load('user'));
    }

    public function update(PaymentRequest $request, Payment $payment): PaymentResource
    {
        $updated = DB::transaction(function () use ($request, $payment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->ensureManualPayment($payment);
            $data = $request->validated();
            $student = $this->payments->lockStudent($data['user_id'] ?? $payment->user_id);
            if (($data['status'] ?? $payment->status) === 'received') {
                $additional = BigDecimal::of((string) ($data['amount'] ?? $payment->amount));
                if ($payment->user_id === $student->id && $payment->status === 'received') {
                    $additional = $additional->minus($payment->amount);
                }
                $this->payments->protectPendingCash($student, $additional);
            }
            $payment->update($data);

            return $payment;
        }, 5);

        return new PaymentResource($updated->load('user'));
    }

    public function destroy(Payment $payment): Response
    {
        $this->ensureManualPayment($payment);
        $payment->delete();

        return response()->noContent();
    }

    public function balance(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payments->balance($request->user())]);
    }

    public function checkout(DemoPaymentRequest $request): PaymentResource
    {
        $payment = $this->payments->checkout($request->user()->id, $request->validated());

        return new PaymentResource($payment->load('user'));
    }

    public function confirm(Payment $payment): PaymentResource
    {
        $updated = $this->payments->decideCashPayment($payment, 'received');

        return new PaymentResource($updated->load('user'));
    }

    public function cancel(Payment $payment): PaymentResource
    {
        Gate::authorize('view', $payment);
        $updated = $this->payments->decideCashPayment($payment, 'void');

        return new PaymentResource($updated->load('user'));
    }

    private function ensureManualPayment(Payment $payment): void
    {
        if ($payment->demo_reference !== null) {
            throw new BusinessException('Демо-платёж защищён от ручного изменения. Для открытой заявки используйте подтверждение или отмену.');
        }
    }
}
