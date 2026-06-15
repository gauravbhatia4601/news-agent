<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('resource')) {
            $query->where('resource_type', $request->resource);
        }

        $logs = $query->paginate(50);

        return response()->json($logs->through(fn ($l) => [
            'id' => $l->id,
            'action' => $l->action,
            'resource_type' => $l->resource_type,
            'resource_id' => $l->resource_id,
            'user_name' => $l->user?->name ?? 'System',
            'ip_address' => $l->ip_address,
            'created_at' => $l->created_at->toIso8601String(),
        ]));
    }
}
