<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly ?string $entityType,
        public readonly ?string $entityId,
        public readonly string $description,
        public readonly ?string $userId = null,
        public readonly ?array $oldValues = null,
        public readonly ?array $newValues = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}
}
