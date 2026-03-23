<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Department;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function users()
    {
        $users = User::with('department')->get();
        
        foreach ($users as $u) {
            $rolesInfo = DB::table('roles')
                ->join('user_roles', 'roles.role_id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $u->user_id)
                ->select('roles.role_id', 'roles.display_name')
                ->get();
            
            $u->roles = $rolesInfo->pluck('display_name')->toArray();
            $roleIds = $rolesInfo->pluck('role_id')->toArray();

            $entityIds = array_merge([$u->user_id], $roleIds);
            $u->permissions = DB::table('permissions')
                ->whereIn('entity_id', $entityIds)
                ->select('section_name', 
                    DB::raw('MAX(can_view) as can_view'), 
                    DB::raw('MAX(can_add) as can_add'), 
                    DB::raw('MAX(can_edit) as can_edit'), 
                    DB::raw('MAX(can_delete) as can_delete'))
                ->groupBy('section_name')
                ->get();
        }
        
        return response()->json($users);
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'first_name' => 'required',
            'last_name' => 'required',
            'department_id' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $deptId = $request->department_id;
            // Handle Custom Department
            if (!DB::table('departments')->where('department_id', $deptId)->exists()) {
                $existingDept = DB::table('departments')->where('department_name', $deptId)->first();
                if (!$existingDept) {
                    $deptId = 'dept-' . substr(md5($request->department_id), 0, 6);
                    DB::table('departments')->insert([
                        'department_id' => $deptId,
                        'department_name' => $request->department_id,
                        'department_code' => strtoupper(substr($request->department_id, 0, 3)),
                        'created_at' => now()
                    ]);
                } else {
                    $deptId = $existingDept->department_id;
                }
            }

            $userId = 'user-' . Str::random(8);
            $user = User::create([
                'user_id' => $userId,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'department_id' => $deptId,
                'is_active' => 1,
            ]);

            if ($request->has('roles')) {
                foreach ($request->roles as $roleName) {
                    $role = DB::table('roles')->where('name', $roleName)->orWhere('display_name', $roleName)->orWhere('role_id', $roleName)->first();
                    $finalRoleId = '';
                    if (!$role) {
                        $finalRoleId = 'role-' . substr(md5($roleName), 0, 6);
                        DB::table('roles')->insert([
                            'role_id' => $finalRoleId,
                            'name' => str_replace(' ', '_', strtolower($roleName)),
                            'display_name' => $roleName,
                            'created_at' => now()
                        ]);
                    } else {
                        $finalRoleId = $role->role_id;
                    }
                    DB::table('user_roles')->insert(['user_id' => $userId, 'role_id' => $finalRoleId]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'User created successfully', 'user_id' => $userId]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create user: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->is_active = $request->is_active ? 1 : 0;
        $user->save();
        return response()->json(['message' => 'Status updated']);
    }

    public function resetPassword(Request $request, $id)
    {
        $request->validate(['password' => 'required|min:8']);
        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();
        return response()->json(['message' => 'Password reset successful']);
    }

    public function deleteUser($id)
    {
        DB::table('user_roles')->where('user_id', $id)->delete();
        User::findOrFail($id)->delete();
        return response()->json(['message' => 'User deleted']);
    }

    public function getPermissions($id)
    {
        return response()->json(DB::table('permissions')->where('entity_id', $id)->get());
    }

    public function savePermissions(Request $request, $id)
    {
        $permissions = $request->permissions ?? [];
        $entityType = $request->entity_type ?? 'user';

        foreach ($permissions as $section => $p) {
            DB::table('permissions')->updateOrInsert(
                ['permission_id' => $id . '_' . str_replace(' ', '_', $section)],
                [
                    'entity_id' => $id,
                    'entity_type' => $entityType,
                    'section_name' => $section,
                    'can_view' => $p['view'] ? 1 : 0,
                    'can_add' => $p['add'] ? 1 : 0,
                    'can_edit' => $p['edit'] ? 1 : 0,
                    'can_delete' => $p['delete'] ? 1 : 0,
                    'created_at' => now()
                ]
            );
        }
        return response()->json(['message' => 'Permissions saved successfully']);
    }

    public function auditLogs()
    {
        $sql = "
            SELECT 
                al.action, 
                CONCAT(u.first_name, ' ', u.last_name) as initiator, 
                al.created_at as timestamp, 
                al.ip_address as ip, 
                'success' as status 
            FROM activity_logs al
            JOIN users u ON al.user_id = u.user_id
            
            UNION ALL
            
            SELECT 
                CONCAT('Document Uploaded: ', rd.file_name) as action, 
                CONCAT(u.first_name, ' ', u.last_name) as initiator, 
                rd.created_at as timestamp, 
                'Local-Storage' as ip, 
                'success' as status
            FROM risk_documents rd
            JOIN users u ON rd.uploaded_by = u.user_id
            
            UNION ALL
            
            SELECT 
                'New User Account Created' as action, 
                'System' as initiator, 
                created_at as timestamp, 
                'Internal' as ip, 
                'success' as status
            FROM users
            
            ORDER BY timestamp DESC
            LIMIT 100
        ";
        
        return response()->json(DB::select($sql));
    }
}
