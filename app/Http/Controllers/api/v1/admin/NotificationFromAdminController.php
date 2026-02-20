<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Customer;
use App\Models\NotificationFromAdmin;
use App\Notifications\admins\NotificationFromAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationFromAdminController extends Controller
{
    public function index()
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-notifications_from_admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $notifications = NotificationFromAdmin::latest()->paginate();

        return response()->json([
            'success' => true,
            'message' => __('responses.all notifications from admins'),
            'notifications' => $notifications,
        ]);
    }

    public function show($notification_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-notifications_from_admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $notification = NotificationFromAdmin::findOrFail($notification_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.notification from admin'),
            'notification' => $notification,
        ]);
    }

    public function store(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('create-notifications_from_admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'title' => 'required|string|max:255',
            'desc' => 'required|string',
            'target' => 'required|in:all,specific',
            'type' => 'required|in:default,schedule',
            'schedule_at' => 'required_if:type,schedule|date_format:Y-m-d H:i:s',
            'target_data' => 'required_if:target,specific|array|exists:customers,id',
        ]);

        if ($request->type == 'schedule' && $request->schedule_at < now()) {
            return response()->json([
                'success' => false,
                'message' => __('responses.schedule_at must be greater than now'),
            ], 400);
        }
        $topic = null;
        try {
            DB::beginTransaction();
            $notification_from_admin = NotificationFromAdmin::create([
                'title' => $request->title,
                'desc' => $request->desc,
                'target' => $request->target,
                'type' => $request->type,
                'schedule_at' => $request->schedule_at,
                'target_data' => $request->target_data,
            ]);
            if ($request->target == 'all') {
                $notification_from_admin->update([
                    'target_data' => null,
                ]);
            }
            if ($request->type == 'default') {
                $notification_from_admin->update([
                    'schedule_at' => null,
                ]);
                if ($request->target == 'specific') {
                    $recipients = Customer::where('status', true)->whereIn('id', $request->target_data)->get();
                    $topic = 'specific_users';
                } else {
                    $recipients = Customer::where('status', true)->get();
                    $topic = 'customers';
                }
                $notification = new NotificationFromAdminNotification($notification_from_admin);
                $fcmTitleKey = $request->title;
                $fcmBodyKey = $request->desc;
                $fcmNotificationTypeData = [
                    'type' => 'notification_from_admin',
                ];
                if ($recipients) {
                    SendNotificationJob::dispatch($recipients, $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData, $topic)->onQueue('notifications');
                }
                $notification_from_admin->update([
                    'is_sent' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.done'),
                'notification' => $notification_from_admin,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function destroy($notification_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('delete-notifications_from_admins')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $notification = NotificationFromAdmin::findOrFail($notification_id);
        try {
            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => __('responses.notification from admin deleted successfully'),
                'notification' => $notification,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('responses.you cannot delete notification from admin'),
            ], 400);
        }
    }
}
