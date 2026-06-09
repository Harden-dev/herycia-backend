<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(
        protected AuditLog $auditLogModel,
    ) {}

    public function create(array $data): AuditLog
    {
        return $this->auditLogModel->create($data);
    }
}
