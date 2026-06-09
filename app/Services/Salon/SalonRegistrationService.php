<?php

namespace App\Services\Salon;

use App\Data\Salon\SalonRegisterData;
use App\Enums\SalonStaffRole;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PlanNotFoundException;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\Contracts\PlanRepositoryInterface;
use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Repositories\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalonRegistrationService
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
        private UserRepositoryInterface $userRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PlanRepositoryInterface $planRepository,
        private SalonSlugGenerator $slugGenerator,
        private SalonDefaultScheduleService $defaultScheduleService,
    ) {}

    /**
     * @return array{salon: Salon, user: User, subscription: Subscription}
     */
    public function register(SalonRegisterData $data): array
    {
        return DB::transaction(function () use ($data) {
            $salon = $this->salonRepository->create([
                'name' => $data->salonName,
                'slug' => $this->slugGenerator->generate($data->salonName),
                'whatsapp_number' => $data->whatsappNumber,
                'city' => $data->city,
                'is_active' => true,
            ]);

            $user = $this->userRepository->create([
                'salon_id' => $salon->id,
                'name' => $data->adminName,
                'phone' => $data->phone,
                'password' => $data->password,
                'role' => SalonStaffRole::Admin,
                'is_active' => true,
            ]);

            $plan = $this->planRepository->findRegisterableByCode($data->planCode);
            if ($plan === null) {
                throw new PlanNotFoundException($data->planCode);
            }

            $trialEndsAt = Carbon::now()
                ->addDays(config('salono.subscription_trial_days', 7))
                ->toDateString();

            $subscription = $this->subscriptionRepository->create([
                'salon_id' => $salon->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trial,
                'is_trial' => true,
                'trial_ends_at' => $trialEndsAt,
                'started_at' => null,
                'ends_at' => null,
            ]);

            $subscription->setRelation('plan', $plan);

            $this->defaultScheduleService->seedForSalon($salon->id);

            return compact('salon', 'user', 'subscription');
        });
    }
}
