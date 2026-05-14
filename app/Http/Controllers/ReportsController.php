<?php

namespace App\Http\Controllers;

use App\Models\BcmPlan;
use App\Models\LossEvent;
use App\Models\Risk;
use App\Models\SheEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportsController extends Controller
{
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
}
