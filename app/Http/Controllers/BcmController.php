<?php

namespace App\Http\Controllers;

use App\Models\BcmPlan;
use App\Models\Risk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BcmController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeModule($request, 'Business Continuity', 'view');

        return response()->json(
            BcmPlan::with(['risk:id,sn,risk_description', 'owner:user_id,first_name,last_name,email'])
                ->orderBy('created_at', 'DESC')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $this->authorizeModule($request, 'Business Continuity', 'add');

        $data = $request->validate([
            'plan_name' => 'required|string|max:255',
            'critical_process' => 'required|string|max:255',
            'dependencies' => 'nullable|string',
            'department_id' => 'nullable|string|max:36',
            'risk_id' => 'nullable|integer',
            'rto_hours' => 'required|integer|min:0',
            'rpo_hours' => 'required|integer|min:0',
            'plan_status' => 'nullable|string|max:50',
            'owner_id' => 'nullable|string|max:36',
            'readiness_score' => 'nullable|integer|min:0|max:100',
            'scenario_test_notes' => 'nullable|string',
            'last_tested' => 'nullable|date',
            'next_test_date' => 'nullable|date',
        ]);

        $data['plan_id'] = (string) Str::uuid();
        $data['plan_reference'] = $this->nextReference();
        $data['owner_id'] = $data['owner_id'] ?? ($request->user()->user_id ?? null);
        $data['plan_status'] = $data['plan_status'] ?? 'Draft';
        $data['readiness_score'] = $data['readiness_score'] ?? 0;

        if (in_array($data['plan_status'], ['Approved', 'Active'], true)) {
            $data['approved_by'] = $request->user()->user_id ?? null;
            $data['approved_at'] = now();
        }

        $plan = BcmPlan::create($data);

        return response()->json([
            'message' => 'Continuity plan recorded successfully',
            'plan' => $plan->load(['risk:id,sn,risk_description', 'owner:user_id,first_name,last_name,email']),
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Business Continuity', 'edit');

        $plan = BcmPlan::findOrFail($id);
        $data = $request->validate([
            'plan_name' => 'sometimes|required|string|max:255',
            'critical_process' => 'sometimes|required|string|max:255',
            'dependencies' => 'nullable|string',
            'department_id' => 'nullable|string|max:36',
            'risk_id' => 'nullable|integer',
            'rto_hours' => 'sometimes|required|integer|min:0',
            'rpo_hours' => 'sometimes|required|integer|min:0',
            'plan_status' => 'nullable|string|max:50',
            'owner_id' => 'nullable|string|max:36',
            'readiness_score' => 'nullable|integer|min:0|max:100',
            'scenario_test_notes' => 'nullable|string',
            'last_tested' => 'nullable|date',
            'next_test_date' => 'nullable|date',
        ]);

        if (
            isset($data['plan_status']) &&
            in_array($data['plan_status'], ['Approved', 'Active'], true) &&
            !$plan->approved_at
        ) {
            $data['approved_by'] = $request->user()->user_id ?? null;
            $data['approved_at'] = now();
        }

        $plan->update($data);

        return response()->json([
            'message' => 'Continuity plan updated successfully',
            'plan' => $plan->fresh(['risk:id,sn,risk_description', 'owner:user_id,first_name,last_name,email']),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Business Continuity', 'delete');

        BcmPlan::findOrFail($id)->delete();

        return response()->json(['message' => 'Continuity plan deleted successfully']);
    }

    public function metadata(Request $request)
    {
        $this->authorizeModule($request, 'Business Continuity', 'view');

        return response()->json([
            'nextReference' => $this->nextReference(),
            'risks' => Risk::orderBy('sn')->get(['id', 'sn', 'risk_description', 'department_id']),
            'departments' => DB::table('departments')
                ->orderBy('department_name')
                ->get(['department_id as id', 'department_name as name']),
            'users' => DB::table('users')
                ->orderBy('first_name')
                ->get(['user_id', 'first_name', 'last_name', 'email']),
        ]);
    }

    private function nextReference(): string
    {
        $year = date('Y');
        $count = BcmPlan::where('plan_reference', 'LIKE', "$year-BCM-%")->count() + 1;
        return sprintf('%s-BCM-%03d', $year, $count);
    }
}
