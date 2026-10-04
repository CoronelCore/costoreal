<?php

namespace App\Modules\Audit;

use App\Models\AuditEvent;

final class AuditEventData
{
    public static function summary(AuditEvent $event): array
    {
        return ['id' => (int) $event->id, 'action' => $event->action, 'revision' => (int) $event->revision,
            'reason' => $event->reason, 'summary' => self::states($event->summary), 'actor' => $event->actor?->only(['id', 'name']), 'created_at' => $event->created_at->toISOString()];
    }

    public static function states(?array $summary): ?array
    {
        if ($summary === null) {
            return null;
        }
        foreach (['before', 'after'] as $side) {
            if (! is_array($summary[$side] ?? null)) {
                continue;
            }
            foreach (['reference_rate', 'cost'] as $field) {
                $value = $summary[$side][$field] ?? null;
                $summary[$side][$field] = $value === null ? null : (string) $value;
            }
        }

        return $summary;
    }
}
