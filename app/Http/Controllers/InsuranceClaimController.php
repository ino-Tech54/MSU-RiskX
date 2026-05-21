<?php

namespace App\Http\Controllers;

use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InsuranceClaimController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'view');

        $claims = InsuranceClaim::with(['department', 'documents'])
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total' => $claims->count(),
            'open' => $claims->where('status', 'Open')->count(),
            'quotation' => $claims->where('status', 'Quotation')->count(),
            'approved' => $claims->where('status', 'Approved')->count(),
            'completed' => $claims->where('status', 'Completed')->count(),
            'under_investigation' => $claims->where('status', 'Under Investigation')->count(),
        ];

        return response()->json([
            'claims' => $claims,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'add');

        $data = $request->validate([
            'date_received' => 'nullable|date',
            'claim_type' => 'nullable|string|max:100',
            'claim_description' => 'nullable|string|max:500',
            'quotation_1' => 'nullable|string|max:255',
            'quotation_2' => 'nullable|string|max:255',
            'quotation_3' => 'nullable|string|max:255',
            'police_report' => 'nullable|string|in:YES,NO,N/A',
            'drivers_licence' => 'nullable|string|in:YES,NO,N/A',
            'pictures' => 'nullable|string|in:YES,NO',
            'release_form' => 'nullable|string|in:YES,NO',
            'status' => 'nullable|string|max:50',
            'pop' => 'nullable|string|in:YES,NO,RECEIVED,N/A',
            'department_id' => 'nullable|string|max:36',
            'claimant_name' => 'nullable|string|max:255',
            'claim_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $data['claim_id'] = (string) Str::uuid();
        $data['claim_number'] = $this->nextClaimNumber();
        $data['reported_by'] = $request->user()->user_id ?? null;
        $data['status'] = $data['status'] ?? 'Open';

        $claim = InsuranceClaim::create($data);
        $this->logActivity($request, 'Insurance Claim Created', 'Created claim ' . $claim->claim_number . ': ' . $claim->claim_description);

        return response()->json([
            'message' => 'Insurance claim recorded successfully',
            'claim' => $claim->load(['department', 'documents']),
        ]);
    }

    public function show(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'view');

        $claim = InsuranceClaim::with(['department', 'documents.uploader'])->findOrFail($id);
        return response()->json(['claim' => $claim]);
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'edit');

        $claim = InsuranceClaim::findOrFail($id);

        $data = $request->validate([
            'date_received' => 'nullable|date',
            'claim_type' => 'nullable|string|max:100',
            'claim_description' => 'nullable|string|max:500',
            'quotation_1' => 'nullable|string|max:255',
            'quotation_2' => 'nullable|string|max:255',
            'quotation_3' => 'nullable|string|max:255',
            'police_report' => 'nullable|string|in:YES,NO,N/A',
            'drivers_licence' => 'nullable|string|in:YES,NO,N/A',
            'pictures' => 'nullable|string|in:YES,NO',
            'release_form' => 'nullable|string|in:YES,NO',
            'status' => 'nullable|string|max:50',
            'pop' => 'nullable|string|in:YES,NO,RECEIVED,N/A',
            'department_id' => 'nullable|string|max:36',
            'claimant_name' => 'nullable|string|max:255',
            'claim_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $claim->update($data);
        $this->logActivity($request, 'Insurance Claim Updated', 'Updated claim ' . $claim->claim_number);

        return response()->json([
            'message' => 'Insurance claim updated successfully',
            'claim' => $claim->load(['department', 'documents']),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'delete');

        $claim = InsuranceClaim::findOrFail($id);
        $claimNumber = $claim->claim_number;

        // Delete associated documents from storage
        foreach ($claim->documents as $doc) {
            Storage::disk('public')->delete($doc->file_path);
            $doc->delete();
        }

        $claim->delete();
        $this->logActivity($request, 'Insurance Claim Deleted', 'Deleted claim ' . $claimNumber);

        return response()->json(['message' => 'Insurance claim deleted successfully']);
    }

    public function uploadDocument(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'edit');

        $claim = InsuranceClaim::findOrFail($id);

        $data = $request->validate([
            'document_type' => 'required|string|in:police_report,drivers_licence,pictures,release_form,quotation,proof_of_payment,other',
            'file' => 'required|file|max:10240', // Max 10MB
            'description' => 'nullable|string|max:500',
        ]);

        $file = $request->file('file');
        $path = $file->store('insurance_claims/' . $id, 'public');

        $document = InsuranceClaimDocument::create([
            'document_id' => (string) Str::uuid(),
            'claim_id' => $id,
            'document_type' => $data['document_type'],
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->user_id ?? null,
            'description' => $data['description'] ?? null,
        ]);

        $this->logActivity($request, 'Document Uploaded', 'Uploaded ' . $data['document_type'] . ' for claim ' . $claim->claim_number);

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document' => $document,
        ]);
    }

    public function deleteDocument(Request $request, string $claimId, string $documentId)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'delete');

        $document = InsuranceClaimDocument::where('claim_id', $claimId)->where('document_id', $documentId)->firstOrFail();

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        $this->logActivity($request, 'Document Deleted', 'Deleted document from claim ' . $claimId);

        return response()->json(['message' => 'Document deleted successfully']);
    }

    public function import(Request $request)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'add');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $rows = [];
        if (in_array($extension, ['xlsx', 'xls'])) {
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

        // Map friendly headers to database fields
        $headerMap = [
            'date claim received' => 'date_received',
            'claim' => 'claim_description',
            'quotation 1' => 'quotation_1',
            'quotation 2' => 'quotation_2',
            'quotation 3' => 'quotation_3',
            'police technical report' => 'police_report',
            'police/ technical report' => 'police_report',
            'driver licence' => 'drivers_licence',
            'driver\'s licence' => 'drivers_licence',
            'pictures' => 'pictures',
            'release form' => 'release_form',
            'status' => 'status',
            'pop' => 'pop',
            'department' => 'department',
            'claimant name' => 'claimant_name',
            'claim value' => 'claim_value',
        ];

        $rawHeaders = array_map(fn($h) => strtolower(trim($h ?? '')), $rows[0]);
        $headers = [];
        foreach ($rawHeaders as $idx => $raw) {
            $headers[$idx] = $headerMap[$raw] ?? $raw;
        }
        $dataRows = array_slice($rows, 1);

        // Build department lookup
        $departments = DB::table('departments')->get(['department_id', 'department_name']);
        $deptLookup = [];
        foreach ($departments as $d) {
            $deptLookup[strtolower(trim($d->department_name))] = $d->department_id;
        }

        $imported = 0;
        $skipped = 0;
        $userId = $request->user()->user_id ?? null;

        foreach ($dataRows as $row) {
            $filledCells = array_filter($row, fn($v) => $v !== null && trim((string)$v) !== '');
            if (count($filledCells) < 2) {
                $skipped++;
                continue;
            }

            $claimDesc = $this->getCell($row, $headers, 'claim_description');
            if (!$claimDesc) {
                $skipped++;
                continue;
            }

            // Determine claim type from description
            $claimType = $this->detectClaimType($claimDesc);

            // Parse date
            $dateReceived = $this->getCell($row, $headers, 'date_received');
            $parsedDate = null;
            if ($dateReceived) {
                try {
                    if (is_numeric($dateReceived)) {
                        $parsedDate = \Carbon\Carbon::createFromTimestamp(($dateReceived - 25569) * 86400)->format('Y-m-d');
                    } else {
                        $parsedDate = \Carbon\Carbon::parse($dateReceived)->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    $parsedDate = null;
                }
            }

            // Resolve department if provided
            $deptName = $this->getCell($row, $headers, 'department');
            $claimantName = $this->getCell($row, $headers, 'claimant_name');
            $claimValue = $this->getCell($row, $headers, 'claim_value');
            $deptId = $deptName ? $this->resolveDepartment($deptName, $deptLookup) : null;

            $record = [
                'claim_id' => (string) Str::uuid(),
                'claim_number' => $this->nextClaimNumber($imported),
                'date_received' => $parsedDate,
                'claim_type' => $claimType,
                'claim_description' => $claimDesc,
                'quotation_1' => $this->getCell($row, $headers, 'quotation_1'),
                'quotation_2' => $this->getCell($row, $headers, 'quotation_2'),
                'quotation_3' => $this->getCell($row, $headers, 'quotation_3'),
                'police_report' => $this->normalizeYesNoNa($this->getCell($row, $headers, 'police_report')),
                'drivers_licence' => $this->normalizeYesNoNa($this->getCell($row, $headers, 'drivers_licence')),
                'pictures' => $this->normalizeYesNo($this->getCell($row, $headers, 'pictures')),
                'release_form' => $this->normalizeYesNo($this->getCell($row, $headers, 'release_form')),
                'status' => $this->normalizeStatus($this->getCell($row, $headers, 'status')),
                'pop' => $this->normalizePop($this->getCell($row, $headers, 'pop')),
                'department_id' => $deptId,
                'claimant_name' => $claimantName,
                'claim_value' => $claimValue ? (float) $claimValue : null,
                'reported_by' => $userId,
            ];

            InsuranceClaim::create($record);
            $imported++;
        }

        $this->logActivity($request, 'Insurance Claims Import', "Imported $imported insurance claims from file ({$file->getClientOriginalName()}). Skipped $skipped empty rows.");

        return response()->json([
            'message' => "$imported claim(s) imported successfully." . ($skipped ? " $skipped empty row(s) skipped." : ''),
            'imported' => $imported,
            'skipped' => $skipped,
        ]);
    }

    public function metadata(Request $request)
    {
        $this->authorizeModule($request, 'Insurance Claims', 'view');

        $departments = DB::table('departments')->select('department_id', 'department_name')->orderBy('department_name')->get();

        return response()->json([
            'departments' => $departments,
            'claimTypes' => ['Motor', 'Property', 'Equipment', 'Fidelity', 'Livestock', 'Public Liability', 'Personal Accident', 'Glass/Windscreen', 'Other'],
            'statuses' => ['Open', 'Quotation', 'Approved', 'Completed', 'Under Investigation', 'Need Clarification'],
        ]);
    }

    private function nextClaimNumber(int $offset = 0): string
    {
        $year = date('Y');
        $count = InsuranceClaim::whereYear('created_at', $year)->count() + 1 + $offset;
        return sprintf('INS/%s/%03d', $year, $count);
    }

    private function getCell(array $row, array $headers, string $key): ?string
    {
        // headers array is now already mapped, so find by key directly
        $index = array_search($key, $headers);
        if ($index === false || !isset($row[$index])) return null;
        $val = trim((string) $row[$index]);
        return $val === '' ? null : $val;
    }

    private function detectClaimType(string $description): string
    {
        $desc = strtolower($description);
        if (str_contains($desc, 'laptop') || str_contains($desc, 'computer') || str_contains($desc, 'cellphone') || str_contains($desc, 'phone')) {
            return 'Equipment';
        }
        if (str_contains($desc, 'motor') || preg_match('/^[a-z]{2,3}\s*\d/i', $description)) {
            return 'Motor';
        }
        if (str_contains($desc, 'fidelity') || str_contains($desc, 'guarantee')) {
            return 'Fidelity';
        }
        if (str_contains($desc, 'livestock') || str_contains($desc, 'tag')) {
            return 'Livestock';
        }
        if (str_contains($desc, 'flood') || str_contains($desc, 'water') || str_contains($desc, 'tank')) {
            return 'Property';
        }
        if (str_contains($desc, 'liability')) {
            return 'Public Liability';
        }
        return 'Other';
    }

    private function normalizeYesNoNa(?string $value): string
    {
        if (!$value) return 'N/A';
        $v = strtoupper(trim($value));
        if (in_array($v, ['YES', 'Y', '1'])) return 'YES';
        if (in_array($v, ['NO', 'N', '0'])) return 'NO';
        return 'N/A';
    }

    private function normalizeYesNo(?string $value): string
    {
        if (!$value) return 'NO';
        $v = strtoupper(trim($value));
        if (in_array($v, ['YES', 'Y', '1'])) return 'YES';
        return 'NO';
    }

    private function normalizePop(?string $value): string
    {
        if (!$value) return 'N/A';
        $v = strtoupper(trim($value));
        if (in_array($v, ['YES', 'Y', '1'])) return 'YES';
        if (in_array($v, ['RECEIVED', 'R'])) return 'RECEIVED';
        if (in_array($v, ['NO', 'N', '0'])) return 'NO';
        return 'N/A';
    }

    private function normalizeStatus(?string $value): string
    {
        if (!$value) return 'Open';
        $v = strtolower(trim($value));
        if (str_contains($v, 'completed') || str_contains($v, 'closed')) return 'Completed';
        if (str_contains($v, 'investigation')) return 'Under Investigation';
        if (str_contains($v, 'quotation')) return 'Quotation';
        if (str_contains($v, 'approved')) return 'Approved';
        if (str_contains($v, 'clarification')) return 'Need Clarification';
        return 'Open';
    }

    private function resolveDepartment(?string $name, array $lookup): ?string
    {
        if (!$name) return null;
        // If already UUID
        if (preg_match('/^[0-9a-f-]{36}$/i', $name)) return $name;
        $key = strtolower(trim($name));
        if (isset($lookup[$key])) return $lookup[$key];
        foreach ($lookup as $deptName => $deptId) {
            if (str_contains($deptName, $key) || str_contains($key, $deptName)) {
                return $deptId;
            }
        }
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
