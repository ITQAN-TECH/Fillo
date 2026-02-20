<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index()
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-roles')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $roles = Role::with('permissions')->latest()->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.all roles'),
            'roles' => $roles,
        ]);
    }

    public function show($role_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-roles')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $role = Role::findOrFail($role_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.role'),
            'role' => $role->load('permissions'),
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-roles')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'nullable|exists:permissions,id',
        ]);
        $role = Role::create([
            'name' => $request->name,
        ]);

        foreach ($request->permissions as $item) {
            $permission = Permission::find($item);
            $role->givePermission($permission);
        }

        return response()->json([
            'success' => true,
            'message' => __('responses.done'),
            'role' => $role->load('permissions'),
        ], 201);
    }

    public function update(Request $request, $role_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-roles')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role_id)],
            'permissions' => 'nullable|array',
            'permissions.*' => 'nullable|exists:permissions,id',
        ]);
        if ($role_id == 1) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $role = Role::findOrFail($role_id);
        $role->update([
            'name' => $request->name ?? $role->name,
        ]);
        $role->syncPermissions($request->permissions ?? []);

        return response()->json([
            'success' => true,
            'message' => __('responses.done'),
            'role' => $role->load('permissions'),
        ]);
    }

    public function destroy($role_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-roles')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        if ($role_id == 1) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $role = Role::findOrFail($role_id);
        try {
            $role->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'role' => $role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function allPermissionsForAdmin()
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-roles')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $permissions = Permission::get();

        return response()->json([
            'success' => true,
            'message' => __('responses.all permissions'),
            'permissions' => $permissions,
        ]);
    }
}
