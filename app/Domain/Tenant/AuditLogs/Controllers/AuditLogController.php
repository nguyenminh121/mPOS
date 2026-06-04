<?php

namespace App\Domain\Tenant\AuditLogs\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Tenant\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'table_name' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $logs = $this->auditLogService->list($request->user(), $validated);

        return response()->json([
            'auditLogs' => $logs,
        ]);
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorize('view', $auditLog);

        return response()->json([
            'auditLog' => $auditLog->load('user:id,name,email'),
        ]);
    }
}
