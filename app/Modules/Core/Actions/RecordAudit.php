<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class RecordAudit
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function handle(string $action, string $entityType, ?int $entityId, ?array $before = null, ?array $after = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
