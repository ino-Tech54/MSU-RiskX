<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Risk;
use Illuminate\Support\Facades\DB;

class RiskController extends Controller
{
    public function index()
    {
        return response()->json(Risk::orderBy('id', 'DESC')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'sn' => 'required',
            'risk_description' => 'required',
            'owner' => 'required',
            'department_id' => 'required',
            'category' => 'required',
        ]);

        $input = $request->all();
        
        // Use the same helper logic for scores
        $input['inherent_risk_score'] = $this->calculateScore($input['inherent_likelihood'] ?? '', $input['inherent_consequence'] ?? '');
        $input['residual_risk_score'] = $this->calculateScore($input['residual_likelihood'] ?? '', $input['residual_consequence'] ?? '');

        $risk = Risk::create($input);
        
        return response()->json(['message' => 'Risk recorded successfully', 'id' => $risk->id]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:risks,id',
        ]);

        $risk = Risk::findOrFail($request->id);
        $input = $request->all();

        // Calculate scores if likelihood/consequence changed
        if (isset($input['inherent_likelihood']) || isset($input['inherent_consequence'])) {
            $input['inherent_risk_score'] = $this->calculateScore(
                $input['inherent_likelihood'] ?? $risk->inherent_likelihood, 
                $input['inherent_consequence'] ?? $risk->inherent_consequence
            );
        }
        if (isset($input['residual_likelihood']) || isset($input['residual_consequence'])) {
            $input['residual_risk_score'] = $this->calculateScore(
                $input['residual_likelihood'] ?? $risk->residual_likelihood, 
                $input['residual_consequence'] ?? $risk->residual_consequence
            );
        }

        $risk->update($input);

        return response()->json(['message' => 'Risk record updated successfully']);
    }

    public function destroy($id)
    {
        $risk = Risk::findOrFail($id);
        $risk->delete();
        return response()->json(['message' => 'Risk record deleted successfully']);
    }

    public function addControl(Request $request)
    {
        $request->validate([
            'risk_id' => 'required',
            'control_measure' => 'required',
            'action' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $controlId = DB::table('risk_controls')->insertGetId([
                'risk_id' => $request->risk_id,
                'control_measure' => $request->control_measure,
                'effectiveness' => $request->effectiveness ?? 'Effective',
                'strategy' => $request->strategy ?? 'Reduce',
                'action' => $request->action,
                'owner' => $request->owner ?? 'Unassigned',
                'deadline' => $request->deadline ?? now()->toDateString(),
                'status' => $request->status ?? 'Open',
                'created_at' => now()
            ]);

            if ($request->has('residual_likelihood') && $request->has('residual_consequence')) {
                $risk = Risk::where('id', $request->risk_id)->orWhere('sn', $request->risk_id)->first();
                if ($risk) {
                    $l = $request->residual_likelihood;
                    $c = $request->residual_consequence;
                    $score = $this->calculateScore($l, $c);
                    
                    $risk->update([
                        'residual_likelihood' => $l,
                        'residual_consequence' => $c,
                        'residual_risk_score' => $score
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Control added and risk updated successfully', 'id' => $controlId]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to add control: ' . $e->getMessage()], 500);
        }
    }

    public function getMetadata()
    {
        $defaultCategories = ["Strategic", "Operational", "Technological", "Projects", "Legal & Compliance"];
        $dbCategories = Risk::whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category')->toArray();
        $categories = array_values(array_unique(array_merge($defaultCategories, $dbCategories)));
        
        $departments = Risk::whereNotNull('department_id')->where('department_id', '!=', '')->distinct()->pluck('department_id');
        $owners = Risk::whereNotNull('owner')->where('owner', '!=', '')->distinct()->pluck('owner');
        
        // Auto-generate S/N
        $lastSn = Risk::where('sn', 'LIKE', 'R-%')->orderByRaw('CAST(SUBSTRING(sn, 3) AS UNSIGNED) DESC')->value('sn');
        $nextNum = 1;
        if ($lastSn && preg_match('/R-(\d+)/', $lastSn, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        }
        $nextSn = sprintf("R-%03d", $nextNum);

        return response()->json([
            'nextSn' => $nextSn,
            'categories' => $categories,
            'departments' => $departments,
            'owners' => $owners
        ]);
    }

    private function calculateScore($likelihood, $consequence) {
        $scoreMap = [
            'VERY LOW' => 1, 'LOW' => 2, 'TOLERABLE' => 3, 'HIGH' => 4, 'EXTREME' => 5,
            'INSIGNIFICANT' => 1, 'MINOR' => 2, 'MODERATE' => 3, 'MAJOR' => 4, 'CATASTROPHIC' => 5,
            'RARE' => 1, 'UNLIKELY' => 2, 'POSSIBLE' => 3, 'LIKELY' => 4, 'ALMOST CERTAIN' => 5
        ];
        $l_score = $scoreMap[strtoupper($likelihood ?? 'POSSIBLE')] ?? 3;
        $c_score = $scoreMap[strtoupper($consequence ?? 'MODERATE')] ?? 3;
        return $l_score * $c_score;
    }
}
