<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\SubDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderByRaw("FIELD(type,'Faculty','Non-Faculty')")
            ->orderBy('department_name')
            ->with('subDepartments')
            ->get()
            ->map(fn($d) => [
                'id'   => $d->department_id,
                'name' => $d->department_name,
                'type' => $d->type ?? 'Non-Faculty',
                'sub_departments' => $d->subDepartments->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                ])->values(),
            ]);

        return response()->json($departments);
    }

    public function subDepartments(string $departmentId)
    {
        $subs = SubDepartment::where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($subs);
    }

    public function resolveSubDepartment(string $departmentId, string $name): ?string
    {
        $sub = SubDepartment::where('department_id', $departmentId)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->first();
        return $sub?->id;
    }

    public static function findSubDeptId(string $departmentId, string $name): ?string
    {
        $sub = SubDepartment::where('department_id', $departmentId)
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower(trim($name)) . '%'])
            ->first();
        return $sub?->id;
    }

    public static function findDeptId(string $name): ?string
    {
        $dept = Department::whereRaw('LOWER(department_name) LIKE ?', ['%' . strtolower(trim($name)) . '%'])
            ->first();
        return $dept?->department_id;
    }

    // ── Department CRUD ────────────────────────────────────────────────────────

    public function storeDepartment(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'type' => 'required|in:Faculty,Non-Faculty',
        ]);

        $name = trim($request->name);
        if (Department::whereRaw('LOWER(department_name) = ?', [strtolower($name)])->exists()) {
            return response()->json(['message' => 'A department with this name already exists.'], 422);
        }

        $id = (string) Str::uuid();
        $dept = Department::create([
            'department_id'   => $id,
            'department_name' => $name,
            'department_code' => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 6)) . rand(10, 99),
            'type'            => $request->type,
        ]);

        $this->logActivity($request, 'Department Created', "Created department: {$name}");
        return response()->json([
            'id'   => $dept->department_id,
            'name' => $dept->department_name,
            'type' => $dept->type,
            'sub_departments' => [],
        ], 201);
    }

    public function updateDepartment(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:255',
            'type' => 'required|in:Faculty,Non-Faculty',
        ]);

        $dept = Department::findOrFail($id);
        $dept->update([
            'department_name' => trim($request->name),
            'type'            => $request->type,
        ]);

        $this->logActivity($request, 'Department Updated', "Updated department: {$dept->department_name}");
        return response()->json([
            'id'   => $dept->department_id,
            'name' => $dept->department_name,
            'type' => $dept->type,
        ]);
    }

    public function destroyDepartment(Request $request, string $id)
    {
        $dept = Department::findOrFail($id);
        $name = $dept->department_name;

        // Detach any users assigned to this department to avoid FK violation
        DB::table('users')->where('department_id', $id)->update([
            'department_id'     => null,
            'sub_department_id' => null,
        ]);

        SubDepartment::where('department_id', $id)->delete();
        $dept->delete();
        $this->logActivity($request, 'Department Deleted', "Deleted department: {$name}");
        return response()->json(['message' => 'Department deleted.']);
    }

    // ── Sub-Department CRUD ────────────────────────────────────────────────────

    public function storeSubDepartment(Request $request, string $departmentId)
    {
        $request->validate(['name' => 'required|string|min:2|max:255']);
        Department::findOrFail($departmentId);

        $name = trim($request->name);
        if (SubDepartment::where('department_id', $departmentId)
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()) {
            return response()->json(['message' => 'Sub-department with this name already exists.'], 422);
        }

        $sub = SubDepartment::create([
            'id'            => (string) Str::uuid(),
            'department_id' => $departmentId,
            'name'          => $name,
        ]);

        $this->logActivity($request, 'Sub-Department Created', "Created sub-dept: {$name}");
        return response()->json(['id' => $sub->id, 'name' => $sub->name], 201);
    }

    public function updateSubDepartment(Request $request, string $departmentId, string $subId)
    {
        $request->validate(['name' => 'required|string|min:2|max:255']);
        $sub = SubDepartment::where('department_id', $departmentId)->findOrFail($subId);
        $sub->update(['name' => trim($request->name)]);
        $this->logActivity($request, 'Sub-Department Updated', "Updated sub-dept: {$sub->name}");
        return response()->json(['id' => $sub->id, 'name' => $sub->name]);
    }

    public function destroySubDepartment(Request $request, string $departmentId, string $subId)
    {
        $sub = SubDepartment::where('department_id', $departmentId)->findOrFail($subId);
        $name = $sub->name;
        $sub->delete();
        $this->logActivity($request, 'Sub-Department Deleted', "Deleted sub-dept: {$name}");
        return response()->json(['message' => 'Sub-department deleted.']);
    }
}
