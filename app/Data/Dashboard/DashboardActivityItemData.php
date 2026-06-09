<?php

namespace App\Data\Dashboard;

readonly class DashboardActivityItemData
{
    public function __construct(
        public string $id,
        public string $type,
        public string $message,
        public string $createdAt,
    ) {}
}
