<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function logActivity(?Request $request, string $action, string $details = '', string $status = 'success', ?string $userId = null): void
    {
        try {
            $user = $request ? $request->user() : null;

            $record = [
                'user_id' => $userId ?? ($user->user_id ?? null),
                'action' => $action,
                'details' => $details,
                'ip_address' => $request ? $request->ip() : null,
                'created_at' => now()
            ];

            if (Schema::hasColumn('activity_logs', 'status')) {
                $record['status'] = $status;
            }

            DB::table('activity_logs')->insert($record);
        } catch (\Throwable $e) {
        }
    }

    protected function authorizeModule(Request $request, string $section, string $action = 'view'): void
    {
        if (!$this->hasModulePermission($request, $section, $action)) {
            abort(403, 'You do not have permission to access this module action.');
        }
    }

    protected function hasModulePermission(Request $request, string $section, string $action = 'view'): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        $fieldMap = [
            'view'    => 'can_view',
            'add'     => 'can_add',
            'create'  => 'can_add',
            'edit'    => 'can_edit',
            'update'  => 'can_edit',
            'delete'  => 'can_delete',
            'approve' => 'can_approve',
        ];
        $field = $fieldMap[strtolower($action)] ?? 'can_view';

        $roleIds = DB::table('user_roles')
            ->where('user_id', $user->user_id)
            ->pluck('role_id')
            ->toArray();

        $entityIds = array_merge([$user->user_id], $roleIds);

        $permission = DB::table('permissions')
            ->whereIn('entity_id', $entityIds)
            ->whereRaw('LOWER(section_name) = ?', [strtolower($section)])
            ->select(DB::raw("MAX($field) as allowed"))
            ->first();

        return (int)($permission->allowed ?? 0) === 1;
    }
}
