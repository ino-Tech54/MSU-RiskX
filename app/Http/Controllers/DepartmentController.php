<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\SubDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
