<?php

namespace App\Repositories\Eloquent;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QueueEntryStatus;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function countAppointmentsOnDate(string $salonId, \DateTimeInterface $date, bool $excludeCancelled = true): int
    {
        $day = Carbon::parse($date);
        $query = Appointment::query()
            ->where('salon_id', $salonId)
            ->whereBetween('scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);

        if ($excludeCancelled) {
            $query->whereNotIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow]);
        }

        return $query->count();
    }

    public function countActiveClientsSince(string $salonId, \DateTimeInterface $since): int
    {
        return Client::query()
            ->where('salon_id', $salonId)
            ->where('last_visit_at', '>=', $since)
            ->count();
    }

    public function countActiveClientsInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): int {
        return Client::query()
            ->where('salon_id', $salonId)
            ->whereBetween('last_visit_at', [$from, $to])
            ->count();
    }

    public function countNewClientsBetween(string $salonId, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return Client::query()
            ->where('salon_id', $salonId)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    public function countNewClientsByMonth(string $salonId, int $year): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = Client::query()
                ->selectRaw("CAST(strftime('%m', created_at) AS INTEGER) as month, COUNT(*) as total")
                ->where('salon_id', $salonId)
                ->whereNotNull('created_at')
                ->whereRaw("strftime('%Y', created_at) = ?", [(string) $year])
                ->groupBy('month')
                ->pluck('total', 'month');
        } else {
            $rows = Client::query()
                ->selectRaw('EXTRACT(MONTH FROM created_at) as month, COUNT(*) as total')
                ->where('salon_id', $salonId)
                ->whereNotNull('created_at')
                ->whereYear('created_at', $year)
                ->groupBy('month')
                ->pluck('total', 'month');
        }

        $result = array_fill(1, 12, 0);
        foreach ($rows as $month => $total) {
            $result[(int) $month] = (int) $total;
        }

        return $result;
    }

    public function countNewClientsByYear(string $salonId, int $fromYear, int $toYear): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = Client::query()
                ->selectRaw("CAST(strftime('%Y', created_at) AS INTEGER) as year, COUNT(*) as total")
                ->where('salon_id', $salonId)
                ->whereNotNull('created_at')
                ->whereRaw('CAST(strftime(\'%Y\', created_at) AS INTEGER) BETWEEN ? AND ?', [$fromYear, $toYear])
                ->groupBy('year')
                ->pluck('total', 'year');
        } else {
            $rows = Client::query()
                ->selectRaw('EXTRACT(YEAR FROM created_at) as year, COUNT(*) as total')
                ->where('salon_id', $salonId)
                ->whereNotNull('created_at')
                ->whereBetween(DB::raw('EXTRACT(YEAR FROM created_at)'), [$fromYear, $toYear])
                ->groupBy('year')
                ->pluck('total', 'year');
        }

        $result = [];
        for ($year = $fromYear; $year <= $toYear; $year++) {
            $result[$year] = (int) ($rows[$year] ?? 0);
        }

        return $result;
    }

    public function sumPaidRevenueBetween(string $salonId, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) Payment::query()
            ->where('salon_id', $salonId)
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');
    }

    public function sumPaidRevenueOnDate(string $salonId, \DateTimeInterface $date): int
    {
        $day = Carbon::parse($date);

        return $this->sumPaidRevenueBetween($salonId, $day->copy()->startOfDay(), $day->copy()->endOfDay());
    }

    public function getRecentActivity(string $salonId, int $limit): array
    {
        $items = [];

        Appointment::query()
            ->where('salon_id', $salonId)
            ->with(['client', 'service'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function (Appointment $appointment) use (&$items): void {
                $clientName = $appointment->client?->name ?? 'Client';
                $serviceName = $appointment->service?->name ?? 'Prestation';
                $time = $appointment->scheduled_at?->format('H\hi') ?? '';
                $statusLabel = match ($appointment->status) {
                    AppointmentStatus::Confirmed => 'RDV confirmé',
                    AppointmentStatus::Pending => 'RDV en attente',
                    AppointmentStatus::InProgress => 'RDV en cours',
                    AppointmentStatus::Completed => 'RDV terminé',
                    AppointmentStatus::Cancelled => 'RDV annulé',
                    AppointmentStatus::NoShow => 'Client absent',
                };

                $items[] = [
                    'id' => $appointment->id,
                    'type' => 'appointment',
                    'message' => "{$statusLabel} — {$clientName} · {$serviceName}".($time !== '' ? " à {$time}" : ''),
                    'created_at' => $appointment->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            });

        Payment::query()
            ->where('salon_id', $salonId)
            ->where('status', PaymentStatus::Paid)
            ->orderByDesc('paid_at')
            ->limit($limit)
            ->get()
            ->each(function (Payment $payment) use (&$items): void {
                $methodLabel = match ($payment->method) {
                    PaymentMethod::MobileMoney => 'Orange Money',
                    PaymentMethod::Cash => 'Espèces',
                    PaymentMethod::Card => 'Carte',
                    default => 'Paiement',
                };
                $amount = number_format($payment->amount, 0, ',', ' ');

                $items[] = [
                    'id' => $payment->id,
                    'type' => 'payment',
                    'message' => "Paiement reçu — {$amount} F · {$methodLabel}",
                    'created_at' => $payment->paid_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            });

        Client::query()
            ->where('salon_id', $salonId)
            ->whereNotNull('created_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function (Client $client) use (&$items): void {
                $items[] = [
                    'id' => $client->id,
                    'type' => 'client',
                    'message' => "Nouveau client — {$client->name}",
                    'created_at' => $client->created_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            });

        QueueEntry::query()
            ->where('salon_id', $salonId)
            ->whereIn('status', [QueueEntryStatus::Waiting, QueueEntryStatus::Called])
            ->with(['client', 'service'])
            ->orderByDesc('arrived_at')
            ->limit($limit)
            ->get()
            ->each(function (QueueEntry $entry) use (&$items): void {
                $clientName = $entry->client?->name ?? 'Client';
                $items[] = [
                    'id' => $entry->id,
                    'type' => 'queue',
                    'message' => "{$clientName} ajoutée à la file d'attente",
                    'created_at' => $entry->arrived_at?->toIso8601String() ?? now()->toIso8601String(),
                ];
            });

        usort($items, fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));

        return array_slice($items, 0, $limit);
    }
}
