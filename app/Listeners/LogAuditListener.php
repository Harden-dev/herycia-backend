<?php

namespace App\Listeners;

use App\Events\AuditEvent;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogAuditListener implements ShouldQueue
{
    public function __construct(
        private AuditLogRepositoryInterface $repository,
    ) {}

    public function handle(AuditEvent $event): void
    {
        try {
            $changes = [];
            if ($event->oldValues !== null && $event->newValues !== null) {
                $allKeys = array_unique(array_merge(
                    array_keys($event->oldValues),
                    array_keys($event->newValues),
                ));
                foreach ($allKeys as $field) {
                    $old = $event->oldValues[$field] ?? null;
                    $new = $event->newValues[$field] ?? null;
                    if ($old !== $new) {
                        $changes[] = [
                            'field' => $field,
                            'old_value' => $old === null ? null : (string) $old,
                            'new_value' => $new === null ? null : (string) $new,
                        ];
                    }
                }
            }

            $this->repository->create([
                'user_id' => $event->userId,
                'action' => $event->action,
                'entity_type' => $event->entityType,
                'entity_id' => $event->entityId,
                'description' => $event->description,
                'ip_address' => $event->ipAddress,
                'user_agent' => $event->userAgent,
            ], $changes);
        } catch (\Throwable $e) {
            Log::error('Audit log failed: ' . $e->getMessage(), [
                'action' => $event->action,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
