<?php

namespace App\Repositories\Contracts;

interface DashboardRepositoryInterface
{
    public function countAppointmentsOnDate(string $salonId, \DateTimeInterface $date, bool $excludeCancelled = true): int;

    public function countActiveClientsSince(string $salonId, \DateTimeInterface $since): int;

    public function countActiveClientsInRange(
        string $salonId,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): int;

    public function countNewClientsBetween(string $salonId, \DateTimeInterface $from, \DateTimeInterface $to): int;

    /** @return array<int, int> month (1-12) => count */
    public function countNewClientsByMonth(string $salonId, int $year): array;

    /** @return array<int, int> year => count */
    public function countNewClientsByYear(string $salonId, int $fromYear, int $toYear): array;

    public function sumPaidRevenueBetween(string $salonId, \DateTimeInterface $from, \DateTimeInterface $to): int;

    public function sumPaidRevenueOnDate(string $salonId, \DateTimeInterface $date): int;

    /** @return list<array{id: string, type: string, message: string, created_at: string}> */
    public function getRecentActivity(string $salonId, int $limit): array;
}
