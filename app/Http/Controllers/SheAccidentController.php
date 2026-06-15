<?php

namespace App\Http\Controllers;

use App\Models\SheAccidentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SheAccidentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeModule($request, 'SHE Compliance', 'view');
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

        $record = SheAccidentRecord::create(array_merge(
            $request->only([
                'name_of_injured', 'day_of_week', 'date_of_injury', 'time_of_injury',
                'age', 'designation', 'employment_status', 'nssa_claim_number',
                'description_of_events', 'department', 'manager_supervisor',
                'source_of_injury', 'location_work_area', 'part_of_body_injured',
                'nature_of_injury', 'days_lost', 'medical_treatment', 'corrective_action',
            ]),
            ['iod_number' => $iodNumber]
        ));

        $this->logActivity($request, 'SHE Accident Record Created', 'Created IOD record ' . $iodNumber);
        return response()->json($record, 201);
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeModule($request, 'SHE Compliance', 'edit');
        $record = SheAccidentRecord::findOrFail($id);
        $record->update($request->only([
            'name_of_injured', 'day_of_week', 'date_of_injury', 'time_of_injury',
            'age', 'designation', 'employment_status', 'nssa_claim_number',
            'description_of_events', 'department', 'manager_supervisor',
            'source_of_injury', 'location_work_area', 'part_of_body_injured',
            'nature_of_injury', 'days_lost', 'medical_treatment', 'corrective_action',
        ]));
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

        if (!$request->hasFile('file')) {
            return response()->json(['message' => 'No file provided'], 422);
        }

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        $headerMap = [
            'i.o.d no'                    => 'iod_number',
            'iod no'                      => 'iod_number',
            'iod number'                  => 'iod_number',
            'name of the injured'         => 'name_of_injured',
            'day of the week'             => 'day_of_week',
            'date of injury'              => 'date_of_injury',
            'time of injury'              => 'time_of_injury',
            'age'                         => 'age',
            'designation'                 => 'designation',
            'employment status'           => 'employment_status',
            'nssa claim number'           => 'nssa_claim_number',
            'description of events'       => 'description_of_events',
            'department of injured'       => 'department',
            'department'                  => 'department',
            'manager /supervisor'         => 'manager_supervisor',
            'manager/supervisor'          => 'manager_supervisor',
            'manager supervisor'          => 'manager_supervisor',
            'source of injury'            => 'source_of_injury',
            'location /work area'         => 'location_work_area',
            'location/work area'          => 'location_work_area',
            'location work area'          => 'location_work_area',
            'part of body injured'        => 'part_of_body_injured',
            'nature of injury'            => 'nature_of_injury',
            'days lost'                   => 'days_lost',
            'medical treatment'           => 'medical_treatment',
            'corrective action taken or recommended' => 'corrective_action',
            'corrective action'           => 'corrective_action',
        ];

        $rows = [];

        if ($ext === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            $headers = null;
            while (($row = fgetcsv($handle)) !== false) {
                if (!$headers) {
                    $headers = array_map(fn($h) => strtolower(trim($h)), $row);
                    continue;
                }
                $rows[] = array_combine($headers, array_pad($row, count($headers), null));
            }
            fclose($handle);
        } else {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $sheetData = $sheet->toArray(null, true, true, false);
            $headers = null;
            foreach ($sheetData as $row) {
                if (!$headers) {
                    $headers = array_map(fn($h) => strtolower(trim((string)$h)), $row);
                    continue;
                }
                if (empty(array_filter($row))) continue;
                $rows[] = array_combine($headers, array_pad($row, count($headers), null));
            }
        }

        $imported = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            $mapped = [];
            foreach ($row as $col => $val) {
                $colClean = trim(preg_replace('/\s+/', ' ', strtolower($col)));
                if (isset($headerMap[$colClean])) {
                    $mapped[$headerMap[$colClean]] = trim((string)$val);
                }
            }

            if (empty($mapped['iod_number'])) { $skipped++; continue; }

            if (is_numeric($mapped['date_of_injury'] ?? null)) {
                try {
                    $mapped['date_of_injury'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($mapped['date_of_injury'])->format('Y-m-d');
                } catch (\Exception $e) {}
            } elseif (!empty($mapped['date_of_injury'])) {
                try {
                    $mapped['date_of_injury'] = date('Y-m-d', strtotime($mapped['date_of_injury']));
                } catch (\Exception $e) {}
            }

            SheAccidentRecord::updateOrCreate(
                ['iod_number' => $mapped['iod_number']],
                $mapped
            );
            $imported++;
        }

        $this->logActivity($request, 'SHE Accident Import', "Imported {$imported} records, skipped {$skipped}");
        return response()->json(['message' => "Imported {$imported} records. Skipped {$skipped}."]);
    }
}
