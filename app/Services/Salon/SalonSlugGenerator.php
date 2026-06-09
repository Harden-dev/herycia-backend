<?php

namespace App\Services\Salon;

use App\Repositories\Contracts\SalonRepositoryInterface;
use Illuminate\Support\Str;

class SalonSlugGenerator
{
    public function __construct(
        private SalonRepositoryInterface $salonRepository,
    ) {}

    public function generate(string $salonName): string
    {
        $base = Str::slug(trim($salonName));
        $base = $base !== '' ? $base : 'salon';
        $slug = $base;
        $counter = 2;

        while ($this->salonRepository->existsBySlug($slug)) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
