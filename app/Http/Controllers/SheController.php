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
            return response()->json(['message' => 'SHE record created successfully', 'id' => $event->id]);
        }
    }

    public function getMetadata()
    {
        $categories = SheEvent::whereNotNull('activity_category')->where('activity_category', '!=', '')->distinct()->pluck('activity_category');
        $locations = SheEvent::whereNotNull('location')->where('location', '!=', '')->distinct()->pluck('location');
        $departments = SheEvent::whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department');
        
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
}
