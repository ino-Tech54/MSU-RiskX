<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Risk;
use App\Models\SheEvent;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function isAdminUser($request): bool
    {
        $user = $request->user();
        if (!$user) return false;
        $roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.role_id')
            ->where('user_roles.user_id', $user->user_id)
            ->pluck('roles.name')
            ->map(fn($r) => strtolower(trim($r)))
            ->toArray();
        $adminRoles = ['system admin', 'admin', 'administrator', 'sys_admin', 'director', 'risk_admin'];
        return count(array_intersect($roles, $adminRoles)) > 0;
    }

    private function isSheOfficer($request): bool
    {
        $user = $request->user();
        if (!$user) return false;

        $roles = DB::table('user_roles')
            ->join('roles', 'user_roles.role_id', '=', 'roles.role_id')
            ->where('user_roles.user_id', $user->user_id)
            ->pluck('roles.name')
            ->map(fn($r) => strtolower(trim($r)))
            ->toArray();
        $sheRoles = ['she', 'she officer', 'safety officer', 'she admin', 'she compliance'];
        if (count(array_intersect($roles, $sheRoles)) > 0) return true;

        $sheDept = DB::table('departments')
            ->where(function($q) {
                $q->whereRaw("LOWER(department_name) LIKE '%she%'")
                  ->orWhereRaw("LOWER(department_code) LIKE '%she%'");
            })
            ->first();
        return $sheDept && $user->department_id === $sheDept->department_id;
    }

    public function stats(Request $request)
    {
        try {
            $isAdmin = $this->isAdminUser($request);
            $isShe = $this->isSheOfficer($request);
            $userDept = $request->user()?->department_id;

            // Build risk query with department scope for non-admins
            $riskQuery = Risk::query();
            if (!$isAdmin && $userDept) {
                $riskQuery->where('department_id', $userDept);
            }

            $totalRisks = (clone $riskQuery)->count();
            $criticalRisks = (clone $riskQuery)->where('residual_risk_score', '>=', 5)->count();
            $activeSHE = SheEvent::whereNotIn('status', ['Completed', 'Closed'])->count();
            
            $totalInherent = (clone $riskQuery)->sum('inherent_risk_score') ?: 0;
            $totalResidual = (clone $riskQuery)->sum('residual_risk_score') ?: 0;

            $activeUsers = DB::table('users')->where('is_active', 1)->count();
            $totalDepts = DB::table('departments')->count();
            $securityLogs = DB::table('activity_logs')->count();

            // Distributions
            $sheByCategory = SheEvent::select('activity_category as label', DB::raw('COUNT(*) as value'))
                ->groupBy('label')->get();

            $sheByStatus = SheEvent::select('status as label', DB::raw('COUNT(*) as value'))
                ->groupBy('label')->get();

            $risksByLevel = (clone $riskQuery)->select(DB::raw("
                CASE
                    WHEN residual_risk_score >= 15 THEN 'Extreme'
                    WHEN residual_risk_score >= 10 THEN 'High'
                    WHEN residual_risk_score >= 5 THEN 'Tolerable'
                    WHEN residual_risk_score >= 2 THEN 'Low'
                    ELSE 'Very Low'
                END as label"), DB::raw('COUNT(*) as value'))
                ->groupBy('label')->get();

            $riskComp = (clone $riskQuery)->select('category as label',
                DB::raw('ROUND(AVG(inherent_risk_score), 1) as inherent'),
                DB::raw('ROUND(AVG(residual_risk_score), 1) as residual'))
                ->groupBy('label')
                ->orderBy('inherent', 'DESC')
                ->limit(6)->get();

            $risksByStatus = (clone $riskQuery)->select('status as label', DB::raw('COUNT(*) as value'))
                ->groupBy('label')->get();

            $deptSHE = SheEvent::select('department as label', DB::raw('COUNT(*) as value'))
                ->groupBy('label')->orderBy('value', 'DESC')->limit(5)->get();

            $deptRisk = (clone $riskQuery)->select('department_id as label', DB::raw('COUNT(*) as value'))
                ->groupBy('label')->orderBy('value', 'DESC')->limit(5)->get();

            return response()->json([
                'biz' => [
                    ['label' => 'Total Risks', 'value' => (string)$totalRisks, 'trend' => 'Live', 'color' => '#3b82f6'],
                    ['label' => 'Critical Risks', 'value' => (string)$criticalRisks, 'trend' => 'High', 'color' => '#ef4444'],
                    ['label' => 'Active SHE Cases', 'value' => (string)$activeSHE, 'trend' => 'Update', 'color' => '#10b981'],
                ],
                'admin' => [
                    ['label' => 'Active Users', 'value' => (string)$activeUsers, 'trend' => 'System', 'color' => '#10b981'],
                    ['label' => 'Total Departments', 'value' => (string)$totalDepts, 'trend' => 'Org', 'color' => '#3b82f6'],
                    ['label' => 'Critical Risks', 'value' => (string)$criticalRisks, 'trend' => 'Risk', 'color' => '#ef4444'],
                    ['label' => 'Security Audit', 'value' => (string)$securityLogs, 'trend' => 'Logs', 'color' => '#8b5cf6'],
                ],
                'charts' => [
                    'she_by_category' => $sheByCategory,
                    'she_by_status' => $sheByStatus,
                    'risks_by_level' => $risksByLevel,
                    'risks_by_status' => $risksByStatus,
                    'risk_comparison' => $riskComp,
                    'portfolio_stats' => [
                        'totalInherent' => (float)$totalInherent,
                        'totalResidual' => (float)$totalResidual
                    ],
                    'she_by_department' => $deptSHE,
                    'risks_by_department' => $deptRisk
                ],
                'recent_she' => SheEvent::orderBy('created_at', 'DESC')->limit(5)->get(['action_id', 'activity_category', 'priority', 'status']),
                'recent_risks' => (clone $riskQuery)->orderBy('created_at', 'DESC')->limit(5)->get(['sn', 'category', 'residual_risk_score', 'status'])
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Dashboard aggregation failed: ' . $e->getMessage()], 500);
        }
    }
}
