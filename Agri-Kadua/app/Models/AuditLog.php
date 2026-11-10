<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tamper-evident, append-only audit trail (FR-12).
 * Replaces the SQL triggers: rows get a SHA-256 hash on insert and can never
 * be updated or deleted through Eloquent.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'audit_id';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            $attributes = $log->getAttributes();

            $log->row_hash = hash('sha256', implode('|', [
                $attributes['user_id'] ?? '',
                $attributes['actor_label'] ?? '',
                $attributes['module'] ?? '',
                $attributes['record_ref'] ?? '',
                $attributes['action'] ?? '',
                $attributes['reason'] ?? '',
                $attributes['before_value'] ?? '',
                $attributes['after_value'] ?? '',
                $attributes['changed_at'] ?? '',
            ]));
        });

        static::updating(function () {
            throw new LogicException('audit_logs is append-only: updates are not allowed');
        });

        static::deleting(function () {
            throw new LogicException('audit_logs is append-only: deletes are not allowed');
        });
    }

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public static function record(
        string $module,
        string $recordRef,
        string $action,
        ?string $reason = null,
        ?string $before = null,
        ?string $after = null,
        ?int $userId = null,
        ?string $actorLabel = null,
        ?int $terminalId = null,
        ?string $changedAt = null,
    ): self {
        return static::create([
            'user_id' => $userId,
            'actor_label' => $actorLabel,
            'module' => $module,
            'record_ref' => $recordRef,
            'action' => $action,
            'reason' => $reason,
            'before_value' => $before,
            'after_value' => $after,
            'terminal_id' => $terminalId,
            'changed_at' => $changedAt ?? now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}