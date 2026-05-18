<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with('department')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->logActivity($request, 'Failed Login', 'Failed login attempt for email ' . $request->email, 'failed', $user->user_id ?? null);
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if ($user->is_active === 0) {
            $this->logActivity($request, 'Failed Login', 'Inactive account login attempt for email ' . $request->email, 'failed', $user->user_id);
            return response()->json(['error' => 'Account inactive. Please contact system admin.'], 403);
        }

        // Fetch Roles
        $roles = DB::table('roles')
            ->join('user_roles', 'roles.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->user_id)
            ->pluck('name')
            ->toArray();

        // Fetch Role IDs for permissions
        $roleIds = DB::table('user_roles')
            ->where('user_id', $user->user_id)
            ->pluck('role_id')
            ->toArray();

        // Fetch Permissions
        $entityIds = array_merge([$user->user_id], $roleIds);
        $permissions = DB::table('permissions')
            ->whereIn('entity_id', $entityIds)
            ->select('section_name',
                DB::raw('MAX(can_view) as can_view'),
                DB::raw('MAX(can_add) as can_add'),
                DB::raw('MAX(can_edit) as can_edit'),
                DB::raw('MAX(can_delete) as can_delete'),
                DB::raw('MAX(can_approve) as can_approve'))
            ->groupBy('section_name')
            ->get();

        $this->logActivity($request, 'User Login', 'Successful authentication via Laravel API', 'success', $user->user_id);

        $token = $user->createToken('auth_token', ['*'], now()->addHours(8));

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token->plainTextToken,
            'expires_at' => now()->addHours(8)->toISOString(),
            'user' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'department_name' => $user->department->department_name ?? 'Unspecified',
                'roles' => $roles,
                'permissions' => $permissions
            ]
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'current_password' => 'required',
            'new_password' => 'required|min:8',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Incorrect current password.'], 401);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        $this->logActivity($request, 'Password Changed', 'User changed password for email ' . $request->email, 'success', $user->user_id);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function getMetadata()
    {
        $depts = DB::table('departments')->select('department_id as id', 'department_name as name')->get();
        $roles = DB::table('roles')->select('role_id as id', 'display_name as name')->get();
        
        return response()->json([
            'departments' => $depts,
            'roles' => $roles
        ]);
    }
}
