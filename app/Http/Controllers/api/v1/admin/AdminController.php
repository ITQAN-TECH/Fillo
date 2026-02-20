<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function index()
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $admins = User::with('roles')->latest()->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.all admins'),
            'admins' => $admins,
        ]);
    }

    public function show($admin_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        if ($admin_id == 1) {
            if (Auth::guard('admins')->id() != 1) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.forbidden'),
                ], 403);
            }
        }
        $admin = User::with('roles')->findOrFail($admin_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.admin'),
            'admin' => $admin,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:255|unique:users,phone',
            'password' => 'required|string|max:255|min:8|confirmed',
            'password_confirmation' => 'required|string|max:255|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:7168',
        ]);
        try {
            DB::beginTransaction();
            $admin = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => $request->password,
            ]);
            if ($request->hasFile('image') && $request->image != null) {
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                $admin->update([
                    'image' => $filename,
                ]);
            }
            if ($request->role_id) {
                $admin->syncRoles([$request->role_id]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'admin' => $admin->load('roles'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }

    }

    public function update(Request $request, $admin_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin_id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'phone')->ignore($admin_id)],
            //            'password' => 'sometimes|nullable|string|max:255|min:8|confirmed',
            //            'password_confirmation' => 'sometimes|nullable|string|max:255|min:8',
            'role_id' => 'sometimes|nullable|exists:roles,id',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:7168',
        ]);
        if ($admin_id == 1) {
            if (Auth::guard('admins')->id() != 1) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.forbidden'),
                ], 403);
            }
            if ($request->role_id != 1) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.forbidden'),
                ], 403);
            }
        }
        $admin = User::findOrFail($admin_id);
        try {
            DB::beginTransaction();
            $admin->update([
                'name' => $request->name ?? $admin->name,
                'email' => $request->email ?? $admin->email,
                'phone' => $request->phone ?? $admin->phone,
                //                'password' => $request->password ?? $admin->password,
            ]);
            if ($request->role_id) {
                $admin->syncRoles([$request->role_id]);
            } else {
                $admin->syncRoles([]);
            }
            if ($request->hasFile('image') && $request->image != null) {
                $name = $request->image->hashName();
                $filename = time().'_'.uniqid().'_'.$name;
                $request->image->storeAs('public/media/', $filename);
                $admin->update([
                    'image' => $filename,
                ]);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'admin' => $admin->load('roles'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function changePassword(Request $request, $admin_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'old_password' => 'required|string|max:255|min:8',
            'password' => 'required|string|max:255|min:8|confirmed',
            'password_confirmation' => 'required|string|max:255|min:8',
        ]);
        if ($admin_id == 1) {
            if (Auth::guard('admins')->id() != 1) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.forbidden'),
                ], 403);
            }
        }
        $admin = User::findOrFail($admin_id);
        try {
            DB::beginTransaction();
            if (! Hash::check($request->old_password, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.The password is incorrect'),
                ], 400);
            }
            $admin->update([
                'password' => $request->password,
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'admin' => $admin->load('roles'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($admin_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        if ($admin_id == 1) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $admin = User::findOrFail($admin_id);
        try {
            $admin->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'admin' => $admin,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete admin'),
            ], 400);
        }
    }

    public function changeStatus(Request $request, $admin_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'status' => 'required|boolean',
        ]);
        if ($admin_id == 1) {
            if (Auth::guard('admins')->id() != 1) {
                return response()->json([
                    'success' => false,
                    'message' => __('responses.forbidden'),
                ], 403);
            } else {
                if ($request->status != true) {
                    return response()->json([
                        'success' => false,
                        'message' => __('responses.forbidden'),
                    ], 403);
                }
            }
        }
        $admin = User::findOrFail($admin_id);
        $admin->update([
            'status' => $request->status,
        ]);

        return response()->noContent();
    }
}
