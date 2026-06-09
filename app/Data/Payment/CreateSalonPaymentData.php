<?php

namespace App\Data\Payment;

use App\Enums\PaymentMethod;
use App\Http\Requests\V1\Payment\CreateSalonPaymentRequest;
use Carbon\Carbon;

readonly class CreateSalonPaymentData
{
    public function __construct(
        public string $appointmentId,
        public int $amount,
        public PaymentMethod $method,
        public ?string $mobileMoneyRef = null,
        public ?Carbon $paidAt = null,
    ) {}

    public static function fromRequest(CreateSalonPaymentRequest $request): self
    {
        return new self(
            appointmentId: $request->string('appointment_id')->toString(),
            amount: $request->integer('amount'),
            method: PaymentMethod::from($request->string('method')->toString()),
            mobileMoneyRef: $request->filled('mobile_money_ref')
                ? $request->string('mobile_money_ref')->toString()
                : null,
            paidAt: $request->filled('paid_at')
                ? Carbon::parse($request->string('paid_at')->toString())
                : null,
        );
    }
}
