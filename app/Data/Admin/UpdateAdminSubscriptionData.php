<?php

namespace App\Data\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\V1\Admin\UpdateAdminSubscriptionRequest;

readonly class UpdateAdminSubscriptionData
{
    public function __construct(
        public ?string $planId = null,
        public ?SubscriptionStatus $status = null,
        public ?string $startedAt = null,
        public ?string $endsAt = null,
        public ?string $trialEndsAt = null,
        public ?bool $isTrial = null,
    ) {}

    public static function fromRequest(UpdateAdminSubscriptionRequest $request): self
    {
        return new self(
            planId: $request->has('plan_id') ? $request->string('plan_id')->toString() : null,
            status: $request->has('status') ? SubscriptionStatus::from($request->string('status')->toString()) : null,
            startedAt: $request->has('started_at') ? $request->input('started_at') : null,
            endsAt: $request->has('ends_at') ? $request->input('ends_at') : null,
            trialEndsAt: $request->has('trial_ends_at') ? $request->input('trial_ends_at') : null,
            isTrial: $request->has('is_trial') ? $request->boolean('is_trial') : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'plan_id' => $this->planId,
            'status' => $this->status,
            'started_at' => $this->startedAt,
            'ends_at' => $this->endsAt,
            'trial_ends_at' => $this->trialEndsAt,
            'is_trial' => $this->isTrial,
        ], fn ($value) => $value !== null);
    }
}
