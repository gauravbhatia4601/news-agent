<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?array $payload = null,
        ?int $userId = null,
    ): void {
        AuditLog::create([
            'user_id' => $userId ?? auth()?->id(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'payload' => $payload,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
