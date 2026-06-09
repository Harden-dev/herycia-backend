<?php

namespace App\Data\Dashboard;

readonly class ChartPointData
{
    public function __construct(
        public string $label,
        public int $value,
    ) {}
}
