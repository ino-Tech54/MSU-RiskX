<?php

namespace App\Http\Controllers;

use App\Models\SheAccidentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SheAccidentController extends Controller
{
    public function index(Request $request)
    {
        if (!$this->hasModulePermission($request, 'SHE Compliance', 'view') &&
            !$this->hasModulePermission($request, 'SHE Management', 'view')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $records = SheAccidentRecord::orderBy('date_of_injury', 'desc')->get();
        return response()->json($records);
    }

    public function store(Request $request)
    {
        $this->authorizeModule($request, 'SHE Compliance', 'add');

        $year = date('Y');
        $last = SheAccidentRecord::where('iod_number', 'like', "IOD/{$year}/%")
            ->orderBy('iod_number', 'desc')->first();

        $seq = 1;
        if ($last) {
            $parts = explode('/', $last->iod_number);
            $seq = ((int) end($parts)) + 1;
        }
        $iodNumber = sprintf('IOD/%s/%03d', $year, $seq);

        $data = $request->only([
            'name_of_injured', 'day_of_week', 'date_of_injury', 'time_of_injury',
            'age', 'designation', 'employment_status', 'nssa_claim_number',
            'description_of_events', 'department', 'manager_supervisor',
            'source_of_injury', 'location_work_area', 'part_of_body_injured',
            'nature_of_injury', 'days_lost', 'medical_treatment', 'corrective_action',
        ]);
        if (isset($data['date_of_injury']) && $data['date_of_injury'] === '') {
            $data['date_of_injury'] = null;
        }
        $record = SheAccidentRecord::create(array_merge($data, ['iod_number' => $iodNumber]));

        $this->logActivity($request, 'SHE Accident Record Created', 'Created IOD record ' . $iodNumber);
        return response()->json($record, 201);
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeModule($request, 'SHE Compliance', 'edit');
        $record = SheAccidentRecord::findOrFail($id);
        $data = $request->only([
            'name_of_injured', 'day_of_week', 'date_of_injury', 'time_of_injury',
            'age', 'designation', 'employment_status', 'nssa_claim_number',
            'description_of_events', 'department', 'manager_supervisor',
            'source_of_injury', 'location_work_area', 'part_of_body_injured',
            'nature_of_injury', 'days_lost', 'medical_treatment', 'corrective_action',
        ]);
        if (isset($data['date_of_injury']) && $data['date_of_injury'] === '') {
            $data['date_of_injury'] = null;
        }
        $record->update($data);
        $this->logActivity($request, 'SHE Accident Record Updated', 'Updated IOD record ' . $record->iod_number);
        return response()->json($record);
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizeModule($request, 'SHE Compliance', 'delete');
        $record = SheAccidentRecord::findOrFail($id);
        $iod = $record->iod_number;
        $record->delete();
        $this->logActivity($request, 'SHE Accident Record Deleted', 'Deleted IOD record ' . $iod);
        return response()->json(['message' => 'Record deleted successfully']);
    }

    public function import(Request $request)
    {
        $this->authorizeModule($request, 'SHE Compliance', 'add');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        $headerMap = [
            'i.o.d no'                               => 'iod_number',
            'iod no'                                  => 'iod_number',
            'iod number'                              => 'iod_number',
            'name of the injured'                     => 'name_of_injured',
            'day of the week'                         => 'day_of_week',
            'date of injury'                          => 'date_of_injury',
            'time of injury'                          => 'time_of_injury',
            'age'                                     => 'age',
            'designation'                             => 'designation',
            'employment status'                       => 'employment_status',
            'nssa claim number'                       => 'nssa_claim_number',
            'description of events'                   => 'description_of_events',
            'department of injured'                   => 'department',
            'department'                              => 'department',
            'manager /supervisor'                     => 'manager_supervisor',
            'manager/supervisor'                      => 'manager_supervisor',
            'manager supervisor'                      => 'manager_supervisor',
            'source of injury'                        => 'source_of_injury',
            'location /work area'                     => 'location_work_area',
            'location/work area'                      => 'location_work_area',
            'location work area'                      => 'location_work_area',
            'part of body injured'                    => 'part_of_body_injured',
            'nature of injury'                        => 'nature_of_injury',
            'days lost'                               => 'days_lost',
            'medical treatment'                       => 'medical_treatment',
            'corrective action taken or recommended'  => 'corrective_action',
            'corrective action'                       => 'corrective_action',
        ];

        $rawRows = [];

        if (in_array($ext, ['xlsx', 'xls'])) {
            if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                return response()->json(['message' => 'Excel upload is not supported on this server. Please export your file as CSV and upload that instead.'], 422);
            }
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                $rawRows[] = $rowData;
            }
        } else {
            $handle = fopen($file->getRealPath(), 'r');
            while (($line = fgetcsv($handle)) !== false) {
                $rawRows[] = $line;
            }
            fclose($handle);
        }

        if (count($rawRows) < 2) {
            return response()->json(['message' => 'File is empty or has no data rows.'], 422);
        }

        $headers = array_map(fn($h) => trim(preg_replace('/\s+/', ' ', strtolower((string)$h))), $rawRows[0]);
        $dataRows = array_slice($rawRows, 1);

        $imported = 0;
        $skipped  = 0;

        foreach ($dataRows as $rowData) {
            if (empty(array_filter($rowData, fn($v) => $v !== null && $v !== ''))) {
                $skipped++;
                continue;
            }

            $row = array_combine($headers, array_pad($rowData, count($headers), null));

            $mapped = [];
            foreach ($row as $col => $val) {
                if (isset($headerMap[$col])) {
                    $cleaned = trim((string)$val);
                    $mapped[$headerMap[$col]] = $cleaned === '' ? null : $cleaned;
                }
            }

            if (empty($mapped['iod_number'])) { $skipped++; continue; }

            // Parse date — handle Excel serial numbers and common date strings
            if (!empty($mapped['date_of_injury'])) {
                if (is_numeric($mapped['date_of_injury']) && class_exists(\PhpOffice\PhpSpreadsheet\Shared\Date::class)) {
                    try {
                        $mapped['date_of_injury'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$mapped['date_of_injury'])->format('Y-m-d');
                    } catch (\Exception $e) { $mapped['date_of_injury'] = null; }
                } else {
                    $parsed = strtotime($mapped['date_of_injury']);
                    $mapped['date_of_injury'] = $parsed ? date('Y-m-d', $parsed) : null;
                }
            } else {
                $mapped['date_of_injury'] = null;
            }

            SheAccidentRecord::updateOrCreate(
                ['iod_number' => $mapped['iod_number']],
                $mapped
            );
            $imported++;
        }

        $this->logActivity($request, 'SHE Accident Import', "Imported {$imported} records, skipped {$skipped}");
        return response()->json([
            'message'  => "Imported {$imported} record(s) successfully." . ($skipped ? " {$skipped} row(s) skipped." : ''),
            'imported' => $imported,
            'skipped'  => $skipped,
        ]);
    }
}
