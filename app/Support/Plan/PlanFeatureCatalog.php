<?php

namespace App\Support\Plan;

use App\Enums\PlanCode;
use App\Models\Plan;

class PlanFeatureCatalog
{
    /** @return list<array{key: string, label: string, included: bool}> */
    public static function featuresFor(Plan $plan): array
    {
        $code = PlanCode::tryFrom($plan->code);

        return match ($code) {
            PlanCode::Basic => self::basicFeatures($plan),
            PlanCode::Pro => self::proFeatures($plan),
            default => self::genericFeatures($plan),
        };
    }

    public static function taglineFor(Plan $plan): ?string
    {
        return match (PlanCode::tryFrom($plan->code)) {
            PlanCode::Basic => 'Petit salon en croissance',
            PlanCode::Pro => 'Salon structuré ou multi-équipe',
            default => null,
        };
    }

    public static function employeesLabelFor(Plan $plan): string
    {
        if ($plan->max_employees === null) {
            return 'Membres du personnel illimités';
        }

        return $plan->max_employees.' membres du personnel';
    }

    /** @return list<array{key: string, label: string, included: bool}> */
    private static function basicFeatures(Plan $plan): array
    {
        return [
            ['key' => 'single_salon', 'label' => '1 salon', 'included' => true],
            ['key' => 'staff', 'label' => self::employeesLabelFor($plan), 'included' => true],
            ['key' => 'agenda', 'label' => 'Agenda + rendez-vous', 'included' => true],
            ['key' => 'clients', 'label' => 'Gestion des clients', 'included' => true],
            ['key' => 'public_booking', 'label' => 'Formulaire public de RDV', 'included' => $plan->has_online_booking],
            ['key' => 'live_tracking', 'label' => 'Suivi RDV client en temps réel', 'included' => true],
            ['key' => 'payments', 'label' => 'Paiements & caisse', 'included' => true],
            ['key' => 'revenue_tracking', 'label' => 'Suivi des recettes', 'included' => false],
            ['key' => 'analytics', 'label' => 'Analytiques avancées', 'included' => false],
            ['key' => 'activity_history', 'label' => 'Historique activité complet', 'included' => false],
        ];
    }

    /** @return list<array{key: string, label: string, included: bool}> */
    private static function proFeatures(Plan $plan): array
    {
        return [
            ['key' => 'all_basic', 'label' => 'Tout Basic inclus', 'included' => true],
            ['key' => 'staff', 'label' => self::employeesLabelFor($plan), 'included' => true],
            ['key' => 'revenue_tracking', 'label' => 'Suivi des recettes', 'included' => true],
            ['key' => 'analytics', 'label' => 'Analytiques avancées', 'included' => $plan->has_analytics],
            ['key' => 'activity_history', 'label' => 'Historique activité complet', 'included' => true],
        ];
    }

    /** @return list<array{key: string, label: string, included: bool}> */
    private static function genericFeatures(Plan $plan): array
    {
        return [
            ['key' => 'staff', 'label' => self::employeesLabelFor($plan), 'included' => true],
            ['key' => 'public_booking', 'label' => 'Formulaire public de RDV', 'included' => $plan->has_online_booking],
            ['key' => 'analytics', 'label' => 'Analytiques avancées', 'included' => $plan->has_analytics],
        ];
    }
}
