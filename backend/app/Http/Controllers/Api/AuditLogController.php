<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AuditLogExport;

class AuditLogController extends Controller
{
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('module')) {
            $query->where('module', $request->input('module'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $needle = '%' . mb_strtolower($search) . '%';

            $query->where(function (Builder $searchQuery) use ($needle) {
                $searchQuery
                    ->whereRaw("LOWER(COALESCE(module, '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(COALESCE(action, '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(COALESCE(CAST(old_values AS TEXT), '')) LIKE ?", [$needle])
                    ->orWhereRaw("LOWER(COALESCE(CAST(new_values AS TEXT), '')) LIKE ?", [$needle])
                    ->orWhereHas('user', function (Builder $userQuery) use ($needle) {
                        $userQuery
                            ->whereRaw("LOWER(COALESCE(username, '')) LIKE ?", [$needle])
                            ->orWhereRaw("LOWER(COALESCE(name, '')) LIKE ?", [$needle])
                            ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE ?", [$needle])
                            ->orWhereRaw("LOWER(COALESCE(role, '')) LIKE ?", [$needle]);
                    })
                    ->orWhereHas('user.employee', function (Builder $employeeQuery) use ($needle) {
                        $employeeQuery
                            ->whereRaw("LOWER(COALESCE(employee_number, '')) LIKE ?", [$needle])
                            ->orWhereRaw("LOWER(COALESCE(first_name, '')) LIKE ?", [$needle])
                            ->orWhereRaw("LOWER(COALESCE(middle_name, '')) LIKE ?", [$needle])
                            ->orWhereRaw("LOWER(COALESCE(last_name, '')) LIKE ?", [$needle])
                            ->orWhereRaw("LOWER(COALESCE(suffix, '')) LIKE ?", [$needle]);
                    });
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $perPage = (int) ($request->input('limit') ?: $request->input('per_page', 50));
        $perPage = max(1, min($perPage, 100));

        $baseQuery = AuditLog::query()
            ->select([
                'id',
                'user_id',
                'module',
                'action',
                'description',
                'model_type',
                'model_id',
                'ip_address',
                'user_agent',
                'old_values',
                'new_values',
                'created_at',
            ])
            ->with([
                'user:id,employee_id,username,name,email,role,avatar',
                'user.employee:id,employee_number,first_name,middle_name,last_name,suffix',
            ]);

        $this->applyFilters($baseQuery, $request);

        $query = (clone $baseQuery)->latest();
        $paginator = $query->paginate($perPage);

        $statsBase = clone $baseQuery;
        $statistics = [
            'total' => (clone $statsBase)->count(),
            'today' => (clone $statsBase)
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'activeUsers' => (clone $statsBase)
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id'),
            'modules' => (clone $statsBase)
                ->whereNotNull('module')
                ->distinct('module')
                ->count('module'),
        ];

        $availableModules = AuditLog::query()
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->values();

        $availableActions = AuditLog::query()
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        return response()->json(array_merge($paginator->toArray(), [
            'statistics' => $statistics,
            'available_modules' => $availableModules,
            'available_actions' => $availableActions,
        ]));
    }

    public function byModule($module)
    {
        $logs = AuditLog::with('user.employee')
            ->where('module', $module)
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }

    public function export(Request $request)
    {
        $query = AuditLog::query()
            ->with('user.employee')
            ->latest();

        $this->applyFilters($query, $request);

        $logs = $query->get();

        return Excel::download(new AuditLogExport($logs), 'audit-logs-' . now()->format('Y-m-d') . '.xlsx');
    }
}
