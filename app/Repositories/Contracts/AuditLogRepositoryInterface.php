<?php

namespace App\Repositories\Contracts;

use App\Models\AuditLog;

interface AuditLogRepositoryInterface
{
    public function create(array $data): AuditLog;
}
