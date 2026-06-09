<?php

namespace Tests\Unit\Support;

use App\Support\IvoryCoastPhone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IvoryCoastPhoneTest extends TestCase
{
    #[DataProvider('normalizationProvider')]
    public function test_normalizes_ivorian_phone_formats(string $input, string $expected): void
    {
        $this->assertSame($expected, IvoryCoastPhone::normalize($input));
        $this->assertTrue(IvoryCoastPhone::isValid($input));
    }

    public static function normalizationProvider(): array
    {
        return [
            'international with plus' => ['+2250748754918', '2250748754918'],
            'international without plus' => ['2250748754918', '2250748754918'],
            'local with leading zero' => ['0748754918', '2250748754918'],
        ];
    }

    public function test_rejects_invalid_phone(): void
    {
        $this->assertFalse(IvoryCoastPhone::isValid('08112233'));
    }
}
