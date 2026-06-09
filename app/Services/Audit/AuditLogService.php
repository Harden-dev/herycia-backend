<?php

namespace App\Services\Audit;

use App\Events\AuditEvent;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function __construct(
        private AuditLogRepositoryInterface $repository,
    ) {}

    public function log(
        string $action,
        ?string $entityType,
        ?string $entityId,
        string $description,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $userId = null,
    ): void {
        $ipAddress = Request::ip();
        $userAgent = Request::userAgent();
        $userId = $userId ?? auth()->id();

        event(new AuditEvent(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            description: $description,
            userId: $userId,
            oldValues: $oldValues,
            newValues: $newValues,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        ));
    }
}
