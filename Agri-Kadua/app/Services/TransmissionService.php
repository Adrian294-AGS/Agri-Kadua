<?php

namespace App\Services;

use App\Models\SyncRecord;

/**
 * Real-time transmission confirmation (FR-05 / NFR-04 / NFR-06).
 * Every record saved through a service is logged here as "saved" once the
 * database write has succeeded.
 */
class TransmissionService
{
    public function confirm(
        string $module,
        string $entityType,
        int $entityId,
        ?int $userId = null,
        ?int $terminalId = null,
        float $sizeKb = 1.0,
    ): SyncRecord {
        $last = SyncRecord::orderByDesc('sync_id')->value('record_code');
        $next = $last ? ((int) substr($last, 3)) + 1 : 9921;

        return SyncRecord::create([
            'record_code' => 'TX-' . $next,
            'source_module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'size_kb' => $sizeKb,
            'status' => 'saved',
            'retry_count' => 0,
            'terminal_id' => $terminalId,
            'created_by' => $userId,
            'synced_at' => now(),
        ]);
    }
}