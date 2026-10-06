<?php

namespace Tests\Unit\Queue;

use App\Services\Queue\QueueSimulator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class QueueSimulatorTest extends TestCase
{
    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-06 '.$time);
    }

    private function simulate(array $args): array
    {
        return (new QueueSimulator)->simulate(
            $args['now'],
            $args['inService'] ?? null,
            $args['called'] ?? [],
            $args['anchored'] ?? [],
            $args['floating'] ?? [],
            $args['reservations'] ?? [],
        );
    }

    public function test_anchored_client_passes_at_appointment_time_after_current_service(): void
    {
        $result = $this->simulate([
            'now' => $this->at('10:00'),
            'inService' => ['id' => 'x', 'started_at' => $this->at('09:50'), 'duration' => 30],
            'anchored' => [['id' => 'a', 'priority_at' => $this->at('10:15'), 'duration' => 30]],
        ]);

        // Le coiffeur finit à 10:20 : le client ancré de 10:15 passe à 10:20.
        $this->assertSame(1, $result['a']['position']);
        $this->assertSame('10:20', $result['a']['estimated_start_at']->format('H:i'));
    }

    public function test_late_client_fills_gap_only_if_service_fits_before_next_appointment(): void
    {
        $result = $this->simulate([
            'now' => $this->at('10:00'),
            'anchored' => [['id' => 'rdv', 'priority_at' => $this->at('10:30'), 'duration' => 30]],
            'floating' => [
                ['id' => 'court', 'priority_at' => $this->at('09:55'), 'duration' => 30],
                ['id' => 'long', 'priority_at' => $this->at('09:58'), 'duration' => 60],
            ],
        ]);

        // « court » tient avant le RDV de 10:30 ; « long » (60 min) passe après.
        $this->assertSame('10:00', $result['court']['estimated_start_at']->format('H:i'));
        $this->assertSame('10:30', $result['rdv']['estimated_start_at']->format('H:i'));
        $this->assertSame('11:00', $result['long']['estimated_start_at']->format('H:i'));
        $this->assertSame([1, 2, 3], [
            $result['court']['position'],
            $result['rdv']['position'],
            $result['long']['position'],
        ]);
    }

    public function test_future_reservation_not_yet_arrived_blocks_its_slot(): void
    {
        $result = $this->simulate([
            'now' => $this->at('10:00'),
            'floating' => [['id' => 'late', 'priority_at' => $this->at('09:50'), 'duration' => 45]],
            'reservations' => [['at' => $this->at('10:30'), 'duration' => 30]],
        ]);

        // 45 min ne tiennent pas avant le RDV réservé de 10:30 (fin 11:00) : passage à 11:00.
        $this->assertSame('11:00', $result['late']['estimated_start_at']->format('H:i'));
    }

    public function test_past_due_unarrived_reservation_does_not_block(): void
    {
        $result = $this->simulate([
            'now' => $this->at('10:10'),
            'floating' => [['id' => 'late', 'priority_at' => $this->at('10:05'), 'duration' => 30]],
            'reservations' => [['at' => $this->at('10:00'), 'duration' => 30]],
        ]);

        $this->assertSame('10:10', $result['late']['estimated_start_at']->format('H:i'));
    }

    public function test_called_clients_go_first_and_late_clients_keep_arrival_order(): void
    {
        $result = $this->simulate([
            'now' => $this->at('14:00'),
            'called' => [['id' => 'appele', 'duration' => 20]],
            'floating' => [
                ['id' => 'second', 'priority_at' => $this->at('13:50'), 'duration' => 30],
                ['id' => 'premier', 'priority_at' => $this->at('13:40'), 'duration' => 30],
            ],
        ]);

        $this->assertSame(1, $result['appele']['position']);
        $this->assertSame(2, $result['premier']['position']);
        $this->assertSame(3, $result['second']['position']);
        $this->assertSame('14:50', $result['second']['estimated_start_at']->format('H:i'));
    }

    public function test_early_anchored_client_is_served_early_when_stylist_is_free(): void
    {
        $result = $this->simulate([
            'now' => $this->at('09:00'),
            'anchored' => [['id' => 'tot', 'priority_at' => $this->at('09:45'), 'duration' => 30]],
        ]);

        $this->assertSame('09:00', $result['tot']['estimated_start_at']->format('H:i'));
    }
}
