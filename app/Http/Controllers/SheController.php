<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SheEvent;
use Illuminate\Support\Facades\DB;

class SheController extends Controller
{
    public function index()
    {
        return response()->json(SheEvent::orderBy('id', 'DESC')->get());
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $eventId = $request->input('id');
        $evidencePath = $request->input('evidence');

        // Handle File Upload
        if ($request->hasFile('evidence_file')) {
            $file = $request->file('evidence_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('public/uploads/she', $fileName);
            $evidencePath = 'storage/uploads/she/' . $fileName;
        }

        if ($eventId) {
            $event = SheEvent::findOrFail($eventId);
            $input['evidence'] = $evidencePath;
            $event->update($input);
            $this->logActivity($request, 'SHE Record Updated', 'Updated SHE record ' . $event->action_id);
            return response()->json(['message' => 'SHE record updated successfully', 'evidence_url' => $evidencePath]);
        } else {
            $request->validate([
                'action_id' => 'required',
                'activity_category' => 'required',
                'location' => 'required',
                'department' => 'required',
                'description' => 'required',
                'owner' => 'required',
            ]);

            $input['evidence'] = $evidencePath;
            $event = SheEvent::create($input);
            $this->logActivity($request, 'SHE Record Created', 'Created SHE record ' . $event->action_id . ': ' . $event->description);
            return response()->json(['message' => 'SHE record created successfully', 'id' => $event->id]);
        }
    }

    public function getMetadata()
    {
        $categories = SheEvent::whereNotNull('activity_category')->where('activity_category', '!=', '')->distinct()->pluck('activity_category');
        $locations = SheEvent::whereNotNull('location')->where('location', '!=', '')->distinct()->pluck('location');
        $departments = DB::table('departments')->select('department_id', 'department_name')->orderBy('department_name')->get();

        $currentYear = date('Y');
        $count = SheEvent::where('action_id', 'LIKE', "$currentYear-SHE-%")->count();
        $nextId = sprintf("%s-SHE-%03d", $currentYear, $count + 1);

        return response()->json([
            'nextId' => $nextId,
            'categories' => $categories,
            'locations' => $locations,
            'departments' => $departments
        ]);
    }

    public function importCsv(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $file   = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return response()->json(['error' => 'File is empty or unreadable.'], 422);
        }
        $headers = array_map('trim', $rawHeaders);

        $requiredColumns = ['description', 'activity_category', 'department'];
        foreach ($requiredColumns as $h) {
            if (!in_array($h, $headers)) {
                fclose($handle);
                return response()->json(['error' => "Missing required column: \"$h\". Please use the provided template."], 422);
            }
        }

        $validPriorities = ['Low', 'Medium', 'High', 'Critical'];
        $validStatuses   = ['Open', 'In Progress', 'Closed', 'Resolved'];

        // Build department name → id lookup
        $departments = DB::table('departments')->get(['department_id', 'department_name']);
        $deptLookup = [];
        foreach ($departments as $d) {
            $deptLookup[strtolower(trim($d->department_name))] = $d->department_id;
        }
        // Also build id → name for reverse lookup
        $deptIdToName = [];
        foreach ($departments as $d) {
            $deptIdToName[$d->department_id] = $d->department_name;
        }

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $rowNum   = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $row = array_map('trim', $row);

            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }
            $data = array_combine($headers, $row);

            $rowLabel  = "Row $rowNum" . (!empty($data['description']) ? " ({$data['description']})" : '');
            $rowErrors = [];

            if (empty($data['description']))       $rowErrors[] = 'Description is required';
            if (empty($data['activity_category'])) $rowErrors[] = 'Activity Category is required';
            if (empty($data['department']))        $rowErrors[] = 'Department is required';

            $priority = $data['priority'] ?? 'Medium';
            if (!in_array($priority, $validPriorities)) {
                $rowErrors[] = "Invalid Priority \"$priority\" (use: " . implode(', ', $validPriorities) . ')';
            }

            $status = $data['status'] ?? 'Open';
            if (!in_array($status, $validStatuses)) $status = 'Open';

            if (!empty($rowErrors)) {
                $skipped++;
                foreach ($rowErrors as $e) {
                    $errors[] = "$rowLabel: $e";
                }
                continue;
            }

            // Resolve department name to ID
            $deptId = $this->resolveDepartmentId($data['department'], $deptLookup);
            $deptName = $deptId ? ($deptIdToName[$deptId] ?? $data['department']) : $data['department'];

            try {
                $currentYear = date('Y');
                $count    = SheEvent::where('action_id', 'LIKE', "$currentYear-SHE-%")->count();
                $actionId = sprintf('%s-SHE-%03d', $currentYear, $count + 1);

                SheEvent::create([
                    'action_id'         => $actionId,
                    'date'              => !empty($data['date']) ? $data['date'] : now()->toDateString(),
                    'quarter'           => $data['quarter'] ?? null,
                    'location'          => $data['location'] ?? null,
                    'department'        => $deptName,
                    'department_id'     => $deptId,
                    'staff_group'       => $data['staff_group'] ?? null,
                    'activity_category' => $data['activity_category'],
                    'reference_id'      => $data['reference_id'] ?? null,
                    'description'       => $data['description'],
                    'observations'      => $data['observations'] ?? null,
                    'recommendations'   => $data['recommendations'] ?? null,
                    'priority'          => $priority,
                    'owner'             => $data['owner'] ?? null,
                    'status'            => $status,
                    'verification'      => $data['verification'] ?? null,
                    'comments'          => $data['comments'] ?? null,
                ]);
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "$rowLabel: " . $e->getMessage();
            }
        }

        fclose($handle);
        $this->logActivity($request, 'SHE Bulk Import', "Imported $imported SHE records via CSV, $skipped skipped.");
        return response()->json(['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors]);
    }

    public function destroy($id)
    {
        $event = SheEvent::findOrFail($id);
        $actionId = $event->action_id;
        $description = $event->description;
        $event->delete();
        $this->logActivity(request(), 'SHE Record Deleted', 'Deleted SHE record ' . $actionId . ': ' . $description);
        return response()->json(['message' => 'SHE record deleted successfully']);
    }

    private function resolveDepartmentId(?string $name, array $lookup): ?string
    {
        if (!$name) return null;
        // If it's already a valid UUID-like ID, return it
        if (preg_match('/^[0-9a-f-]{36}$/i', $name)) {
            return $name;
        }
        $key = strtolower(trim($name));
        // Exact match
        if (isset($lookup[$key])) return $lookup[$key];
        // Partial / fuzzy match
        foreach ($lookup as $deptName => $deptId) {
            if (str_contains($deptName, $key) || str_contains($key, $deptName)) {
                return $deptId;
            }
        }
        // Try comma-separated parts
        $parts = array_map('trim', explode(',', $key));
        foreach ($parts as $part) {
            if (isset($lookup[$part])) return $lookup[$part];
            foreach ($lookup as $deptName => $deptId) {
                if (str_contains($deptName, $part) || str_contains($part, $deptName)) {
                    return $deptId;
                }
            }
        }
        return null;
    }
}
