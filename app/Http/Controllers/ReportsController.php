<?php

namespace App\Http\Controllers;

use App\Models\BcmPlan;
use App\Models\LossEvent;
use App\Models\Risk;
use App\Models\RiskControl;
use App\Models\SheEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportsController extends Controller
{
    private function isAdminUser($request): bool
    {
        $user = $request->user();
        if (!$user) return false;
        $roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.role_id')
            ->where('user_roles.user_id', $user->id)
            ->pluck('roles.name')
            ->map(fn($r) => strtolower(trim($r)))
            ->toArray();
        $adminRoles = ['system admin', 'admin', 'administrator', 'sys_admin', 'director', 'risk_admin'];
        return count(array_intersect($roles, $adminRoles)) > 0;
    }

    private function userDeptId($request): ?string
    {
        $user = $request->user();
        return $user->department_id ?? null;
    }

    private function userDeptName($request): ?string
    {
        $deptId = $this->userDeptId($request);
        if (!$deptId) return null;
        $dept = DB::table('departments')->where('department_id', $deptId)->first();
        return $dept->department_name ?? null;
    }

    public function summary(Request $request)
    {
        $this->authorizeModule($request, 'Analysis & Reports', 'view');

        $hasLosses = Schema::hasTable('loss_events');
        $hasBcm = Schema::hasTable('bcm_plans');

        $lossTrend = $hasLosses
            ? LossEvent::select(DB::raw("DATE_FORMAT(loss_date, '%Y-%m') as label"), DB::raw('SUM(financial_impact) as value'))
                ->groupBy('label')
                ->orderBy('label')
                ->limit(12)
                ->get()
            : collect();

        $bcmStatus = $hasBcm
            ? BcmPlan::select('plan_status as label', DB::raw('COUNT(*) as value'))
                ->groupBy('label')
                ->get()
            : collect();

        $bcmReadiness = $hasBcm
            ? BcmPlan::select('department_id as label', DB::raw('ROUND(AVG(readiness_score), 1) as value'))
                ->groupBy('label')
                ->orderBy('value', 'ASC')
                ->get()
            : collect();

        return response()->json([
            'cards' => [
                ['label' => 'Total Risks', 'value' => Risk::count()],
                ['label' => 'SHE Records', 'value' => SheEvent::count()],
                ['label' => 'Open Loss Events', 'value' => $hasLosses ? LossEvent::whereNotIn('status', ['Closed', 'Resolved'])->count() : 0],
                ['label' => 'Total Loss Value', 'value' => $hasLosses ? (float) LossEvent::sum('financial_impact') : 0],
                ['label' => 'BCM Plans', 'value' => $hasBcm ? BcmPlan::count() : 0],
                ['label' => 'Average BCM Readiness', 'value' => $hasBcm ? round((float) BcmPlan::avg('readiness_score'), 1) : 0],
            ],
            'topRisks' => Risk::orderBy('residual_risk_score', 'DESC')
                ->limit(8)
                ->get(['sn', 'risk_description', 'category', 'department_id', 'residual_risk_score', 'status']),
            'lossTrend' => $lossTrend,
            'lossByDepartment' => $hasLosses
                ? LossEvent::select('department_id as label', DB::raw('SUM(financial_impact) as value'))
                    ->groupBy('label')
                    ->orderBy('value', 'DESC')
                    ->limit(8)
                    ->get()
                : [],
            'treatmentPerformance' => DB::table('risk_controls')
                ->select('status as label', DB::raw('COUNT(*) as value'))
                ->groupBy('label')
                ->get(),
            'bcmStatus' => $bcmStatus,
            'bcmReadiness' => $bcmReadiness,
            'sheCompliance' => SheEvent::select('status as label', DB::raw('COUNT(*) as value'))
                ->groupBy('label')
                ->get(),
        ]);
    }

    public function risks(Request $request)
    {
        $this->authorizeModule($request, 'Analysis & Reports', 'view');

        $query = Risk::with('controls')->orderBy('created_at', 'DESC');

        if (!$this->isAdminUser($request) && $dept = $this->userDeptId($request)) {
            $query->where('department_id', $dept);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return response()->json($query->get());
    }

    public function sheEvents(Request $request)
    {
        $this->authorizeModule($request, 'Analysis & Reports', 'view');

        $query = SheEvent::orderBy('created_at', 'DESC');

        if (!$this->isAdminUser($request) && $deptName = $this->userDeptName($request)) {
            $query->where('department', $deptName);
        } elseif ($request->filled('department')) {
            $query->where('department', $request->department);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return response()->json($query->get());
    }

    public function lossEvents(Request $request)
    {
        $this->authorizeModule($request, 'Analysis & Reports', 'view');

        if (!Schema::hasTable('loss_events')) {
            return response()->json([]);
        }

        $query = LossEvent::with(['risk:id,sn,risk_description', 'sheEvent:id,action_id,activity_category'])
            ->orderBy('created_at', 'DESC');

        if (!$this->isAdminUser($request) && $dept = $this->userDeptId($request)) {
            $query->where('department_id', $dept);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('loss_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('loss_date', '<=', $request->to);
        }

        return response()->json($query->get());
    }

    public function bcmPlans(Request $request)
    {
        $this->authorizeModule($request, 'Analysis & Reports', 'view');

        if (!Schema::hasTable('bcm_plans')) {
            return response()->json([]);
        }

        $query = BcmPlan::with(['risk:id,sn,risk_description', 'owner:user_id,first_name,last_name'])
            ->orderBy('created_at', 'DESC');

        if (!$this->isAdminUser($request) && $dept = $this->userDeptId($request)) {
            $query->where('department_id', $dept);
        } elseif ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return response()->json($query->get());
    }

    public function auditTrail(Request $request)
    {
        $user = $request->user();
        $roles = DB::table('roles')
            ->join('user_roles', 'roles.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->user_id)
            ->pluck('name')
            ->map(fn($r) => strtolower($r))
            ->toArray();

        if (!in_array('system admin', $roles) && !in_array('admin', $roles) && !in_array('administrator', $roles)) {
            return response()->json(['error' => 'Only system administrators can access audit trail reports.'], 403);
        }

        $statusCol = Schema::hasColumn('activity_logs', 'status') ? "COALESCE(al.status, 'success')" : "'success'";

        $sql = "
            SELECT
                al.action,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown/System') as initiator,
                al.created_at as timestamp,
                al.ip_address as ip,
                al.details,
                {$statusCol} as status
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.user_id
        ";

        $bindings = [];
        $wheres = [];

        if ($request->filled('from')) {
            $wheres[] = 'al.created_at >= ?';
            $bindings[] = $request->from . ' 00:00:00';
        }
        if ($request->filled('to')) {
            $wheres[] = 'al.created_at <= ?';
            $bindings[] = $request->to . ' 23:59:59';
        }

        if (count($wheres)) {
            $sql .= ' WHERE ' . implode(' AND ', $wheres);
        }

        $sql .= ' ORDER BY al.created_at DESC LIMIT 500';

        return response()->json(DB::select($sql, $bindings));
    }
}
