<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        string $action,
        Model $subject,
        ?array $before,
        ?array $after,
        ?string $reason = null,
        ?User $actor = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => (int) $subject->getKey(),
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
