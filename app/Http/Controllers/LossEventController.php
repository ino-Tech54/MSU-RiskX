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

        $data = $request->validate([
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
        ]);

        $data['loss_id'] = (string) Str::uuid();
        $data['loss_reference'] = $this->nextReference();
        $data['reported_by'] = $request->user()->user_id ?? null;
        $data['financial_impact'] = $data['financial_impact'] ?? 0;
        $data['status'] = $data['status'] ?? 'Open';

        $event = LossEvent::create($data);

        return response()->json([
            'message' => 'Loss event recorded successfully',
            'event' => $event->load(['risk:id,sn,risk_description', 'sheEvent:id,action_id,activity_category']),
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Loss Events', 'edit');

        $event = LossEvent::findOrFail($id);
        $data = $request->validate([
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
        ]);

        $event->update($data);

        return response()->json([
            'message' => 'Loss event updated successfully',
            'event' => $event->fresh(['risk:id,sn,risk_description', 'sheEvent:id,action_id,activity_category']),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $this->authorizeModule($request, 'Loss Events', 'delete');

        LossEvent::findOrFail($id)->delete();

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
}
