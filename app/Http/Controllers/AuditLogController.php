<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $userId = $request->integer('user_id');
        $actionFilter = $request->string('action')->trim()->toString();
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();

        $query = AuditLog::query()
            ->with([
                'user:id,name,email',
                'subject' => static function (MorphTo $morphTo): void {
                    $morphTo->morphWith([
                        Attendance::class => ['attendable'],
                    ]);
                },
            ])
            ->when($userId > 0, static function ($q) use ($userId): void {
                $q->where('user_id', $userId);
            })
            ->when($actionFilter !== '', static function ($q) use ($actionFilter): void {
                $q->where('action', $actionFilter);
            })
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1, static function ($q) use ($dateFrom): void {
                $q->whereDate('created_at', '>=', $dateFrom);
            })
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1, static function ($q) use ($dateTo): void {
                $q->whereDate('created_at', '<=', $dateTo);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $logs = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log) => (new AuditLogResource($log))->resolve($request));

        $actors = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])
            ->values()
            ->all();

        $actionOptions = [
            ['value' => 'attendance.excused', 'label' => (string) __('ui.audit_actions.attendance.excused')],
            ['value' => 'attendance.corrected', 'label' => (string) __('ui.audit_actions.attendance.corrected')],
            ['value' => 'attendance.auto_absent_overridden', 'label' => (string) __('ui.audit_actions.attendance.auto_absent_overridden')],
        ];

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'actors' => $actors,
            'actionOptions' => $actionOptions,
            'filters' => [
                'user_id' => $userId > 0 ? $userId : null,
                'action' => $actionFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
