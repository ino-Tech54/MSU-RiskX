<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Risk;
use App\Models\RiskControl;
use Illuminate\Support\Facades\DB;

class RiskController extends Controller
{
    public function index()
    {
        return response()->json(Risk::with('controls')->orderBy('id', 'DESC')->get());
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
        $input['sn'] = $this->nextRiskId($input['department_id'] ?? null);
        
        $input['inherent_risk_score'] = $this->calculateScore($input['inherent_likelihood'] ?? '', $input['inherent_consequence'] ?? '');
        $input['residual_risk_score'] = $this->calculateScore($input['residual_likelihood'] ?? '', $input['residual_consequence'] ?? '');
        $input['approval_status'] = 'pending';

        $risk = Risk::create($input);
        $this->logActivity($request, 'Risk Created', 'Created risk ' . $risk->sn . ': ' . $risk->risk_description);
        
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
        $this->logActivity($request, 'Risk Updated', 'Updated risk ' . $risk->sn . ' fields: ' . implode(', ', array_keys($input)));

        return response()->json(['message' => 'Risk record updated successfully']);
    }

    public function destroy($id)
    {
        $risk = Risk::findOrFail($id);
        $sn = $risk->sn;
        $description = $risk->risk_description;
        $risk->delete();
        $this->logActivity(request(), 'Risk Deleted', 'Deleted risk ' . $sn . ': ' . $description);
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
            $this->logActivity($request, 'Risk Control Added', 'Added control to risk ' . $request->risk_id . ': ' . $request->control_measure);
            return response()->json(['message' => 'Control added and risk updated successfully', 'id' => $controlId]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->logActivity($request, 'Failed Risk Control Add', 'Failed adding control to risk ' . $request->risk_id . ': ' . $e->getMessage(), 'failed');
            return response()->json(['error' => 'Failed to add control: ' . $e->getMessage()], 500);
        }
    }

    public function controls(Request $request)
    {
        return response()->json(RiskControl::orderBy('created_at', 'DESC')->get());
    }

    public function destroyControl(Request $request, $id)
    {
        $control = RiskControl::findOrFail($id);
        $description = $control->control_measure;
        $control->delete();
        $this->logActivity($request, 'Risk Control Deleted', 'Deleted risk control ' . $id . ': ' . $description);

        return response()->json(['message' => 'Control removed successfully']);
    }

    public function importCsv(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        $headers = array_map('trim', fgetcsv($handle));
        $expectedHeaders = ['risk_description', 'category', 'department_id', 'owner'];
        foreach ($expectedHeaders as $h) {
            if (!in_array($h, $headers)) {
                fclose($handle);
                return response()->json(['error' => "Missing required column: $h"], 422);
            }
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) { $skipped++; continue; }
            $data = array_combine($headers, array_map('trim', $row));

            if (empty($data['risk_description']) || empty($data['category']) || empty($data['department_id']) || empty($data['owner'])) {
                $skipped++;
                $errors[] = "Row skipped (missing required fields): " . ($data['risk_description'] ?? '(empty)');
                continue;
            }

            try {
                $likelihoodMap = ['RARE' => 1, 'UNLIKELY' => 2, 'POSSIBLE' => 3, 'LIKELY' => 4, 'ALMOST CERTAIN' => 5];
                $consequenceMap = ['INSIGNIFICANT' => 1, 'MINOR' => 2, 'MODERATE' => 3, 'MAJOR' => 4, 'CATASTROPHIC' => 5];

                $iL = strtoupper(trim($data['inherent_likelihood'] ?? 'POSSIBLE'));
                $iC = strtoupper(trim($data['inherent_consequence'] ?? 'MODERATE'));
                $rL = strtoupper(trim($data['residual_likelihood'] ?? 'UNLIKELY'));
                $rC = strtoupper(trim($data['residual_consequence'] ?? 'MODERATE'));

                Risk::create([
                    'sn'                       => $this->nextRiskId($data['department_id']),
                    'date_reviewed'            => $data['date_reviewed'] ?? now()->toDateString(),
                    'risk_description'         => $data['risk_description'],
                    'category'                 => $data['category'],
                    'department_id'            => $data['department_id'],
                    'owner'                    => $data['owner'],
                    'kra_at_risk'              => $data['kra_at_risk'] ?? null,
                    'causes'                   => $data['causes'] ?? null,
                    'consequence'              => $data['consequence'] ?? null,
                    'inherent_likelihood'      => $iL,
                    'inherent_consequence'     => $iC,
                    'inherent_risk_score'      => ($likelihoodMap[$iL] ?? 3) * ($consequenceMap[$iC] ?? 3),
                    'existing_controls'        => $data['existing_controls'] ?? null,
                    'control_effectiveness'    => $data['control_effectiveness'] ?? 'Moderate',
                    'residual_likelihood'      => $rL,
                    'residual_consequence'     => $rC,
                    'residual_risk_score'      => ($likelihoodMap[$rL] ?? 2) * ($consequenceMap[$rC] ?? 3),
                    'mitigation_strategy'      => $data['mitigation_strategy'] ?? 'Reduce',
                    'action_treatment'         => $data['action_treatment'] ?? null,
                    'method'                   => $data['method'] ?? 'Risk Monitoring',
                    'resolved_by'              => $data['resolved_by'] ?? null,
                    'status'                   => $data['status'] ?? 'Open',
                    'likelihood_justification' => $data['likelihood_justification'] ?? null,
                    'consequence_justification'=> $data['consequence_justification'] ?? null,
                    'approval_status'          => 'pending',
                ]);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Row failed (" . ($data['risk_description'] ?? '') . "): " . $e->getMessage();
            }
        }

        fclose($handle);
        $this->logActivity($request, 'Risk Bulk Import', "Imported $imported risks via CSV, $skipped skipped.");
        return response()->json(['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);
    }

    public function approve(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'exists:risks,id']);

        $user = $request->user();
        $userName = ($user->first_name ?? '') . ' ' . ($user->last_name ?? $user->username ?? 'Unknown');

        $updated = Risk::whereIn('id', $request->ids)->update([
            'approval_status' => 'hod_approved',
            'approved_by'     => trim($userName),
            'approved_at'     => now(),
            'rejection_reason' => null,
        ]);

        $this->logActivity($request, 'Risk Approved', 'HOD approved ' . $updated . ' risk(s): IDs ' . implode(',', $request->ids), 'success');
        return response()->json(['message' => $updated . ' risk(s) approved successfully.']);
    }

    public function reject(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array',
            'ids.*'  => 'exists:risks,id',
            'reason' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $userName = ($user->first_name ?? '') . ' ' . ($user->last_name ?? $user->username ?? 'Unknown');

        $updated = Risk::whereIn('id', $request->ids)->update([
            'approval_status'  => 'rejected',
            'approved_by'      => trim($userName),
            'approved_at'      => now(),
            'rejection_reason' => $request->reason,
        ]);

        $this->logActivity($request, 'Risk Rejected', 'HOD rejected ' . $updated . ' risk(s): ' . $request->reason, 'failed');
        return response()->json(['message' => $updated . ' risk(s) rejected.']);
    }

    public function dueReviews()
    {
        $cutoff = now()->addDays(20)->toDateString();
        $today  = now()->toDateString();

        $risks = Risk::whereIn('status', ['Open', 'In Progress'])
            ->whereNotNull('resolved_by')
            ->whereBetween('resolved_by', [$today, $cutoff])
            ->select('id', 'sn', 'risk_description', 'owner', 'department_id', 'resolved_by', 'status')
            ->orderBy('resolved_by')
            ->get();

        return response()->json($risks);
    }

    public function getMetadata()
    {
        $defaultCategories = ["Strategic", "Operational", "Technological", "Projects", "Legal & Compliance"];
        $dbCategories = Risk::whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category')->toArray();
        $categories = array_values(array_unique(array_merge($defaultCategories, $dbCategories)));
        
        $departments = Risk::whereNotNull('department_id')->where('department_id', '!=', '')->distinct()->pluck('department_id');
        $owners = Risk::whereNotNull('owner')->where('owner', '!=', '')->distinct()->pluck('owner');
        
        $nextSn = $this->nextRiskId(request()->query('department_id'));

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

    private function nextRiskId($department): string
    {
        $code = $this->departmentCode($department);
        $prefix = $code . '-R';
        $lastSn = Risk::where('sn', 'LIKE', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(sn, ?) AS UNSIGNED) DESC', [strlen($prefix) + 1])
            ->value('sn');
        $nextNum = 1;

        if ($lastSn && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastSn, $matches)) {
            $nextNum = (int)$matches[1] + 1;
        }

        return sprintf('%s%03d', $prefix, $nextNum);
    }

    private function departmentCode($department): string
    {
        $value = trim((string) $department);
        if ($value === '') {
            return 'GEN';
        }

        $dept = DB::table('departments')
            ->where('department_id', $value)
            ->orWhere('department_name', $value)
            ->first();

        if ($dept && !empty($dept->department_code)) {
            return $this->sanitizeCode($dept->department_code);
        }

        $name = $dept->department_name ?? $value;
        $words = preg_split('/\s+/', preg_replace('/[^A-Za-z0-9\s]/', ' ', $name));
        $code = '';

        foreach ($words as $word) {
            if ($word !== '') {
                $code .= strtoupper(substr($word, 0, 1));
            }
        }

        if (strlen($code) < 2) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        }

        return $this->sanitizeCode($code ?: 'GEN');
    }

    private function sanitizeCode(string $code): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        return $code !== '' ? substr($code, 0, 8) : 'GEN';
    }
}
