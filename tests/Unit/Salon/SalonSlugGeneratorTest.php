<?php

namespace Tests\Unit\Salon;

use App\Repositories\Contracts\SalonRepositoryInterface;
use App\Services\Salon\SalonSlugGenerator;
use Mockery;
use Tests\TestCase;

class SalonSlugGeneratorTest extends TestCase
{
    public function test_generates_slug_from_salon_name(): void
    {
        $repository = Mockery::mock(SalonRepositoryInterface::class);
        $repository->shouldReceive('existsBySlug')->once()->with('salon-koffi-cocody')->andReturn(false);

        $generator = new SalonSlugGenerator($repository);

        $this->assertSame('salon-koffi-cocody', $generator->generate('Salon Koffi Cocody'));
    }

    public function test_appends_numeric_suffix_when_slug_exists(): void
    {
        $repository = Mockery::mock(SalonRepositoryInterface::class);
        $repository->shouldReceive('existsBySlug')->once()->with('salon-koffi-cocody')->andReturn(true);
        $repository->shouldReceive('existsBySlug')->once()->with('salon-koffi-cocody-2')->andReturn(false);

        $generator = new SalonSlugGenerator($repository);

        $this->assertSame('salon-koffi-cocody-2', $generator->generate('Salon Koffi Cocody'));
    }

    public function test_handles_accents_and_special_characters(): void
    {
        $repository = Mockery::mock(SalonRepositoryInterface::class);
        $repository->shouldReceive('existsBySlug')->once()->with('coiffure-elegance')->andReturn(false);

        $generator = new SalonSlugGenerator($repository);

        $this->assertSame('coiffure-elegance', $generator->generate('Coiffure Élégance'));
    }
}
