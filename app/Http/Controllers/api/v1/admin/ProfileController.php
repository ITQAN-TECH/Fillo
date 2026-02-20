<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function editProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore(Auth::guard('admins')->id())],
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg|max:7168',
        ]);
        $admin = Auth::guard('admins')->user();
        $admin->update([
            'name' => $request->name ?? $admin->name,
            'email' => $request->email ?? $admin->email,
        ]);
        if ($request->hasFile('image') && $request->image != null) {
            $name = $request->image->hashName();
            $filename = time().'_'.uniqid().'_'.$name;
            $request->image->storeAs('public/media/', $filename);
            $admin->update([
                'image' => $filename,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('responses.done'),
            'employee' => $admin,
        ]);
    }

    public function editPassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string|min:8|max:255',
            'password' => 'required|string|min:8|max:255',
            'password_confirmation' => 'required|same:password|string|min:8|max:255',
        ]);
        $admin = Auth::guard('admins')->user();
        if (! Hash::check($request->old_password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => __('responses.credentials are incorrect'),
            ], 400);
        }
        $admin->update([
            'password' => $request->password,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('responses.done'),
            'employee' => $admin,
        ]);
    }

    public function show()
    {
        $admin = Auth::guard('admins')->user();

        return response()->json([
            'success' => true,
            'message' => __('responses.admin'),
            'admin' => $admin->load('roles.permissions'),
        ]);

    }
}
