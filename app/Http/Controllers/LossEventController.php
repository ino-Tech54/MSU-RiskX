<?php

namespace App\Http\Controllers;

use App\Models\LossEvent;
use App\Models\Risk;
use App\Models\SheEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LossEventController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeModule($request, 'Loss Events', 'view');

        return response()->json(
            LossEvent::with(['risk:id,sn,risk_description', 'sheEvent:id,action_id,activity_category'])
                ->orderBy('loss_date', 'DESC')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $this->authorizeModule($request, 'Loss Events', 'add');

        $recordType = $request->input('record_type', 'loss_event');

        $rules = [
            'record_type' => 'nullable|string|in:loss_event,loss_control',
            'loss_date' => 'required|date',
            'event_title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|string|max:36',
            'risk_id' => 'nullable|integer',
            'she_event_id' => 'nullable|integer',
            'financial_impact' => 'nullable|numeric|min:0',
            'non_financial_impact' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'evidence' => 'nullable|string|max:255',
        ];

        if ($recordType === 'loss_control') {
            $rules = array_merge($rules, [
                'priority_level' => 'nullable|string|in:Low,Medium,High,Extreme',
                'complainant' => 'nullable|string|max:255',
                'accused_person' => 'nullable|string|max:255',
                'time_of_occurrence' => 'nullable|date_format:H:i',
                'case_against' => 'nullable|string|max:50',
                'police_ref' => 'nullable|string|max:50',
                'case_category' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'property_involved' => 'nullable|string|max:255',
                'estimate_value' => 'nullable|numeric|min:0',
                'corrective_action' => 'nullable|string',
                'action_owner' => 'nullable|string|max:255',
                'quarter' => 'nullable|string|max:30',
            ]);
        }

        $data = $request->validate($rules);

        $data['loss_id'] = (string) Str::uuid();
        $data['record_type'] = $recordType;
        $data['reported_by'] = $request->user()->user_id ?? null;
        $data['financial_impact'] = $data['financial_impact'] ?? 0;
        $data['status'] = $data['status'] ?? ($recordType === 'loss_control' ? 'Open' : 'Open');

        if ($recordType === 'loss_control') {
            $data['case_number'] = $this->nextCaseNumber();
            $data['loss_reference'] = $data['case_number'];
        } else {
            $data['loss_reference'] = $this->nextReference();
        }

        $event = LossEvent::create($data);
        $this->logActivity($request, 'Loss Event Created', 'Created ' . $recordType . ' ' . $event->loss_reference . ': ' . $event->event_title);

        return response()->json([
            'message' => ($recordType === 'loss_control' ? 'Loss control case recorded successfully' : 'Loss event recorded successfully'),
            'event' => $event->load(['risk:id,sn,risk_description', 'sheEvent:id,action_id,activity_category']),
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Loss Events', 'edit');

        $event = LossEvent::findOrFail($id);
        $isLossControl = $event->record_type === 'loss_control';

        $rules = [
            'loss_date' => 'sometimes|required|date',
            'event_title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'department_id' => 'nullable|string|max:36',
            'risk_id' => 'nullable|integer',
            'she_event_id' => 'nullable|integer',
            'financial_impact' => 'nullable|numeric|min:0',
            'non_financial_impact' => 'nullable|string',
            'root_cause' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'evidence' => 'nullable|string|max:255',
        ];

        if ($isLossControl) {
            $rules = array_merge($rules, [
                'priority_level' => 'nullable|string|in:Low,Medium,High,Extreme',
                'complainant' => 'nullable|string|max:255',
                'accused_person' => 'nullable|string|max:255',
                'time_of_occurrence' => 'nullable|date_format:H:i',
                'case_against' => 'nullable|string|max:50',
                'police_ref' => 'nullable|string|max:50',
                'case_category' => 'nullable|string|max:100',
                'location' => 'nullable|string|max:255',
                'property_involved' => 'nullable|string|max:255',
                'estimate_value' => 'nullable|numeric|min:0',
                'corrective_action' => 'nullable|string',
                'action_owner' => 'nullable|string|max:255',
                'quarter' => 'nullable|string|max:30',
            ]);
        }

        $data = $request->validate($rules);

        $event->update($data);
        $this->logActivity($request, 'Loss Event Updated', 'Updated ' . $event->record_type . ' ' . $event->loss_reference . ' fields: ' . implode(', ', array_keys($data)));

        return response()->json([
            'message' => ($isLossControl ? 'Loss control case updated successfully' : 'Loss event updated successfully'),
            'event' => $event->fresh(['risk:id,sn,risk_description', 'sheEvent:id,action_id,activity_category']),
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizeModule($request, 'Loss Events', 'add');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = [];
        if (in_array($extension, ['xlsx', 'xls'])) {
            // Use PhpSpreadsheet if available, otherwise require CSV
            if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                return response()->json(['message' => 'Please upload a CSV file. Excel support requires additional server packages.'], 422);
            }
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                $rows[] = $rowData;
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            while (($line = fgetcsv($handle)) !== false) {
                $rows[] = $line;
            }
            fclose($handle);
        }

        if (count($rows) < 2) {
            return response()->json(['message' => 'File is empty or has no data rows.'], 422);
        }

        $headers = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
        $dataRows = array_slice($rows, 1);

        // Column mapping
        $map = [
            'case_number' => $this->findColumn($headers, ['case no', 'case_no', 'case number', 'caseno']),
            'priority_level' => $this->findColumn($headers, ['priority', 'priority level', 'priority_level']),
            'complainant' => $this->findColumn($headers, ['complainant', 'name of complainant']),
            'accused_person' => $this->findColumn($headers, ['accused', 'accused person', 'accused_person']),
            'loss_date' => $this->findColumn($headers, ['date of occurrence', 'date', 'loss_date', 'occurrence date']),
            'time_of_occurrence' => $this->findColumn($headers, ['time', 'time of occurrence', 'time_of_occurrence']),
            'case_against' => $this->findColumn($headers, ['case against', 'case_against']),
            'police_ref' => $this->findColumn($headers, ['police ref', 'police_ref', 'police reference']),
            'description' => $this->findColumn($headers, ['case brief', 'description', 'brief']),
            'department_id' => $this->findColumn($headers, ['department', 'affected department', 'affected_department']),
            'case_category' => $this->findColumn($headers, ['case category', 'category', 'case_category']),
            'location' => $this->findColumn($headers, ['location', 'work area', 'location /work area', 'location / work area']),
            'property_involved' => $this->findColumn($headers, ['property', 'property involved', 'property_involved']),
            'estimate_value' => $this->findColumn($headers, ['estimate value', 'estimate_value', 'value']),
            'corrective_action' => $this->findColumn($headers, ['corrective action', 'corrective_action', 'corrective action taken or recommended']),
            'action_owner' => $this->findColumn($headers, ['action owner', 'action_owner']),
            'quarter' => $this->findColumn($headers, ['quarter']),
            'status' => $this->findColumn($headers, ['status']),
        ];

        // Build department name → id lookup
        $departments = DB::table('departments')->get(['department_id', 'department_name']);
        $deptLookup = [];
        foreach ($departments as $d) {
            $deptLookup[strtolower(trim($d->department_name))] = $d->department_id;
        }

        $imported = 0;
        $skipped = 0;
        $userId = $request->user()->user_id ?? null;

        foreach ($dataRows as $row) {
            // Skip empty rows
            $filledCells = array_filter($row, fn($v) => $v !== null && trim($v) !== '');
            if (count($filledCells) < 2) {
                $skipped++;
                continue;
            }

            $caseNum = $this->getCell($row, $map['case_number']);
            $lossDate = $this->getCell($row, $map['loss_date']);
            $desc = $this->getCell($row, $map['description']);

            if (!$lossDate && !$desc && !$caseNum) {
                $skipped++;
                continue;
            }

            // Parse date
            $parsedDate = null;
            if ($lossDate) {
                try {
                    if (is_numeric($lossDate)) {
                        $parsedDate = \Carbon\Carbon::createFromTimestamp(($lossDate - 25569) * 86400)->format('Y-m-d');
                    } else {
                        $parsedDate = \Carbon\Carbon::parse($lossDate)->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    $parsedDate = date('Y-m-d');
                }
            } else {
                $parsedDate = date('Y-m-d');
            }

            // Parse time
            $timeVal = $this->getCell($row, $map['time_of_occurrence']);
            $parsedTime = null;
            if ($timeVal) {
                try {
                    if (is_numeric($timeVal)) {
                        $totalSeconds = round($timeVal * 86400);
                        $parsedTime = gmdate('H:i', $totalSeconds);
                    } else {
                        $parsedTime = \Carbon\Carbon::parse($timeVal)->format('H:i');
                    }
                } catch (\Exception $e) {
                    $parsedTime = null;
                }
            }

            $generatedCaseNum = $caseNum ?: $this->nextCaseNumber($imported);
            $eventTitle = $desc ? Str::limit($desc, 200) : ('Loss Control Case ' . $generatedCaseNum);

            $record = [
                'loss_id' => (string) Str::uuid(),
                'loss_reference' => $generatedCaseNum,
                'record_type' => 'loss_control',
                'case_number' => $generatedCaseNum,
                'priority_level' => $this->normalizePriority($this->getCell($row, $map['priority_level'])),
                'complainant' => $this->getCell($row, $map['complainant']),
                'accused_person' => $this->getCell($row, $map['accused_person']),
                'loss_date' => $parsedDate,
                'time_of_occurrence' => $parsedTime,
                'case_against' => $this->getCell($row, $map['case_against']),
                'police_ref' => $this->getCell($row, $map['police_ref']),
                'event_title' => $eventTitle,
                'description' => $desc,
                'department_id' => $this->resolveDepartment($this->getCell($row, $map['department_id']), $deptLookup),
                'case_category' => $this->getCell($row, $map['case_category']),
                'location' => $this->getCell($row, $map['location']),
                'property_involved' => $this->getCell($row, $map['property_involved']),
                'estimate_value' => is_numeric($this->getCell($row, $map['estimate_value'])) ? $this->getCell($row, $map['estimate_value']) : null,
                'corrective_action' => $this->getCell($row, $map['corrective_action']),
                'action_owner' => $this->getCell($row, $map['action_owner']),
                'quarter' => $this->getCell($row, $map['quarter']),
                'status' => $this->getCell($row, $map['status']) ?: 'Open',
                'reported_by' => $userId,
                'financial_impact' => is_numeric($this->getCell($row, $map['estimate_value'])) ? $this->getCell($row, $map['estimate_value']) : 0,
            ];

            LossEvent::create($record);
            $imported++;
        }

        $this->logActivity($request, 'Loss Control Import', "Imported $imported loss control cases from file ({$file->getClientOriginalName()}). Skipped $skipped empty rows.");

        return response()->json([
            'message' => "$imported case(s) imported successfully." . ($skipped ? " $skipped empty row(s) skipped." : ''),
            'imported' => $imported,
            'skipped' => $skipped,
        ]);
    }

    private function findColumn(array $headers, array $possibleNames): ?int
    {
        foreach ($possibleNames as $name) {
            foreach ($headers as $idx => $header) {
                if (str_contains($header, $name)) {
                    return $idx;
                }
            }
        }
        return null;
    }

    private function getCell(array $row, ?int $index): ?string
    {
        if ($index === null || !isset($row[$index])) return null;
        $val = trim((string) $row[$index]);
        return $val === '' ? null : $val;
    }

    private function resolveDepartment(?string $name, array $lookup): ?string
    {
        if (!$name) return null;
        $key = strtolower(trim($name));
        // Exact match
        if (isset($lookup[$key])) return $lookup[$key];
        // Partial / fuzzy match: check if any department name contains the input or vice-versa
        foreach ($lookup as $deptName => $deptId) {
            if (str_contains($deptName, $key) || str_contains($key, $deptName)) {
                return $deptId;
            }
        }
        // Try matching on individual parts separated by comma
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

    private function normalizePriority(?string $value): ?string
    {
        if (!$value) return null;
        $map = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'extreme' => 'Extreme'];
        return $map[strtolower(trim($value))] ?? ucfirst(strtolower(trim($value)));
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Loss Events', 'delete');

        $event = LossEvent::findOrFail($id);
        $reference = $event->loss_reference;
        $title = $event->event_title;
        $event->delete();
        $this->logActivity($request, 'Loss Event Deleted', 'Deleted loss event ' . $reference . ': ' . $title);

        return response()->json(['message' => 'Loss event deleted successfully']);
    }

    public function metadata(Request $request)
    {
        $this->authorizeModule($request, 'Loss Events', 'view');

        return response()->json([
            'nextReference' => $this->nextReference(),
            'risks' => Risk::orderBy('sn')->get(['id', 'sn', 'risk_description', 'department_id']),
            'sheEvents' => SheEvent::orderBy('action_id')->get(['id', 'action_id', 'activity_category', 'department', 'description']),
            'departments' => DB::table('departments')
                ->orderBy('department_name')
                ->get(['department_id as id', 'department_name as name']),
        ]);
    }

    private function nextReference(): string
    {
        $year = date('Y');
        $count = LossEvent::where('loss_reference', 'LIKE', "$year-LOSS-%")->count() + 1;
        return sprintf('%s-LOSS-%03d', $year, $count);
    }

    private function nextCaseNumber(int $offset = 0): string
    {
        $year = date('Y');
        $count = LossEvent::where('record_type', 'loss_control')
            ->where('case_number', 'LIKE', "IOD/$year/%")
            ->count() + 1 + $offset;
        return sprintf('IOD/%s/%03d', $year, $count);
    }
}
