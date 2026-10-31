<?php

namespace App\Services\Queue;

use Carbon\CarbonImmutable;

/**
 * Simule la journée d'un coiffeur pour estimer l'ordre et l'heure de passage (logique pure, sans base).
 *
 * Règles :
 * - le client en cours occupe le coiffeur jusqu'à sa fin estimée ;
 * - les clients déjà appelés passent ensuite, dans l'ordre d'appel ;
 * - un client « ancré » (arrivé à l'heure) passe à l'heure de son rendez-vous, ou plus tôt si le
 *   coiffeur est libre et que cela ne déborde pas sur un autre rendez-vous ;
 * - un rendez-vous pas encore arrivé (heure future) réserve son créneau ;
 * - un client « flottant » (retardataire placé après le dernier) comble les trous, par ordre
 *   d'arrivée, uniquement si sa prestation tient avant le prochain rendez-vous.
 */
class QueueSimulator
{
    /**
     * @param  array{id: string, started_at: CarbonImmutable, duration: int}|null  $inService
     * @param  list<array{id: string, duration: int}>  $called  dans l'ordre d'appel
     * @param  list<array{id: string, priority_at: CarbonImmutable, duration: int}>  $anchored
     * @param  list<array{id: string, priority_at: CarbonImmutable, duration: int}>  $floating
     * @param  list<array{at: CarbonImmutable, duration: int}>  $reservations  rendez-vous futurs non arrivés
     * @return array<string, array{position: int, estimated_start_at: CarbonImmutable, estimated_end_at: CarbonImmutable}>
     */
    public function simulate(
        CarbonImmutable $now,
        ?array $inService,
        array $called,
        array $anchored,
        array $floating,
        array $reservations,
    ): array {
        $cursor = $now;

        if ($inService !== null) {
            $end = $inService['started_at']->addMinutes($inService['duration']);
            $cursor = $end->greaterThan($now) ? $end : $now;
        }

        usort($anchored, fn (array $a, array $b) => $a['priority_at'] <=> $b['priority_at']);
        usort($floating, fn (array $a, array $b) => $a['priority_at'] <=> $b['priority_at']);
        $reservations = array_values(array_filter(
            $reservations,
            fn (array $r) => $r['at']->greaterThanOrEqualTo($now),
        ));
        usort($reservations, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        $result = [];
        $position = 0;

        $serve = function (string $id, int $duration) use (&$cursor, &$position, &$result): void {
            $position++;
            $start = $cursor;
            $cursor = $cursor->addMinutes($duration);
            $result[$id] = [
                'position' => $position,
                'estimated_start_at' => $start,
                'estimated_end_at' => $cursor,
            ];
        };

        foreach ($called as $entry) {
            $serve($entry['id'], $entry['duration']);
        }

        // Garde-fou : chaque tour sert, consomme une réservation ou avance le curseur.
        $guard = 0;

        while (($anchored !== [] || $floating !== []) && $guard++ < 1000) {
            $nextAnchored = $anchored[0] ?? null;
            $nextReservation = $reservations[0] ?? null;

            // 1. Un client ancré dont l'heure est arrivée passe en premier.
            if ($nextAnchored !== null && $nextAnchored['priority_at']->lessThanOrEqualTo($cursor)) {
                array_shift($anchored);
                $serve($nextAnchored['id'], $nextAnchored['duration']);

                continue;
            }

            // 2. Un rendez-vous réservé dont l'heure est arrivée occupe le coiffeur.
            if ($nextReservation !== null && $nextReservation['at']->lessThanOrEqualTo($cursor)) {
                array_shift($reservations);
                $cursor = $cursor->addMinutes($nextReservation['duration']);

                continue;
            }

            $nextFixed = $this->earliest($nextAnchored['priority_at'] ?? null, $nextReservation['at'] ?? null);
            $gap = $nextFixed !== null ? (int) $cursor->diffInMinutes($nextFixed) : PHP_INT_MAX;

            // 3. Un retardataire comble le trou si sa prestation y tient.
            if ($floating !== [] && $floating[0]['duration'] <= $gap) {
                $entry = array_shift($floating);
                $serve($entry['id'], $entry['duration']);

                continue;
            }

            // 4. Un client ancré en avance passe plus tôt s'il ne déborde pas sur un rendez-vous réservé.
            if ($nextAnchored !== null) {
                $untilReservation = $nextReservation !== null
                    ? (int) $cursor->diffInMinutes($nextReservation['at'])
                    : PHP_INT_MAX;

                if ($nextAnchored['duration'] <= $untilReservation) {
                    array_shift($anchored);
                    $serve($nextAnchored['id'], $nextAnchored['duration']);

                    continue;
                }
            }

            // 5. Sinon le coiffeur attend la prochaine échéance fixe.
            if ($nextFixed !== null) {
                $cursor = $nextFixed;

                continue;
            }

            break;
        }

        return $result;
    }

    private function earliest(?CarbonImmutable $a, ?CarbonImmutable $b): ?CarbonImmutable
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a->lessThanOrEqualTo($b) ? $a : $b;
    }
}
