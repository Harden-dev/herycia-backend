<?php

namespace App\Actions\Payment;

use App\Data\Payment\CreateSalonPaymentData;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Salon\SalonContextService;
use Illuminate\Support\Facades\DB;

class CreateSalonPaymentAction
{
    public function __construct(
        private SalonContextService $salonContext,
        private PaymentRepositoryInterface $paymentRepository,
    ) {}

    public function execute(CreateSalonPaymentData $data): Payment
    {
        $salon = $this->salonContext->resolveAuthenticatedSalon();
        $appointment = $this->salonContext->resolveSalonAppointment($data->appointmentId);

        if ($appointment->status === AppointmentStatus::Cancelled) {
            throw new \RuntimeException('Impossible d\'enregistrer un paiement pour un rendez-vous annulé.');
        }

        if ($this->paymentRepository->existsPaidForAppointment($appointment->id)) {
            throw new \RuntimeException('Un paiement validé existe déjà pour ce rendez-vous.');
        }

        return DB::transaction(function () use ($salon, $appointment, $data): Payment {
            // Verrou sur le rendez-vous puis re-contrôle : empêche deux paiements validés
            // enregistrés simultanément pour le même rendez-vous (audit M3).
            Appointment::query()->whereKey($appointment->id)->lockForUpdate()->first();

            if ($this->paymentRepository->existsPaidForAppointment($appointment->id)) {
                throw new \RuntimeException('Un paiement validé existe déjà pour ce rendez-vous.');
            }

            return $this->paymentRepository->create([
                'salon_id' => $salon->id,
                'appointment_id' => $appointment->id,
                'client_id' => $appointment->client_id,
                'amount' => $data->amount,
                'method' => $data->method,
                'status' => PaymentStatus::Paid,
                'mobile_money_ref' => $data->mobileMoneyRef,
                'paid_at' => $data->paidAt ?? now(),
            ])->load(['client', 'appointment.service']);
        });
    }
}
