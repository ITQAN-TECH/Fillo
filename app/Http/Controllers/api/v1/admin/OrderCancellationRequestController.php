<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\OrderCancellationRequest;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Notifications\customers\CancellationRequestApprovedNotification;
use App\Notifications\customers\CancellationRequestRejectedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderCancellationRequestController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-order-cancellation-requests')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'search' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|in:pending,approved,rejected,all',
        ]);

        $cancellationRequests = OrderCancellationRequest::when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
            $query->where('status', $request->status);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->whereHas('order', function ($query) use ($search) {
                    $query->where('order_number', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                })->orWhereHas('customer', function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            });
        })->latest()->paginate();

        $report = [];
        $report['total_requests'] = OrderCancellationRequest::count();
        $report['pending_requests'] = OrderCancellationRequest::where('status', 'pending')->count();
        $report['approved_requests'] = OrderCancellationRequest::where('status', 'approved')->count();
        $report['rejected_requests'] = OrderCancellationRequest::where('status', 'rejected')->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all cancellation requests'),
            'report' => $report,
            'cancellation_requests' => $cancellationRequests,
        ]);
    }

    public function show($request_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-order-cancellation-requests')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $cancellationRequest = OrderCancellationRequest::findOrFail($request_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.cancellation request'),
            'cancellation_request' => $cancellationRequest,
        ]);
    }

    public function approve(Request $request, $request_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-order-cancellation-requests')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'cancellation_reason' => ['required', 'in:administrative,customer_not_received'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $cancellationRequest = OrderCancellationRequest::findOrFail($request_id);

        if ($cancellationRequest->status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => __('responses.cancellation request is not pending'),
            ], 400);
        }

        $order = $cancellationRequest->order;

        if (in_array($order->order_status, ['cancelled', 'completed', 'refunded'])) {
            return response()->json([
                'success' => false,
                'message' => __('responses.cannot cancel this order'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            foreach ($order->items as $item) {
                $productVariant = ProductVariant::find($item->product_variant_id);
                if ($productVariant) {
                    $productVariant->increment('quantity', $item->quantity);
                }
            }

            $refundAmount = $order->total_price;
            if ($request->cancellation_reason == 'customer_not_received') {
                $refundAmount = $order->total_price - $order->shipping_fee;
            }

            $order->update([
                'order_status' => 'cancelled',
                'cancellation_reason' => $request->cancellation_reason,
                'admin_notes' => $request->admin_notes,
            ]);

            $payment = Payment::where('order_id', $order->id)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'refunded',
                    'refunded_amount' => $refundAmount,
                ]);
            }

            $cancellationRequest->update([
                'status' => 'approved',
                'cancellation_reason' => $request->cancellation_reason,
                'admin_notes' => $request->admin_notes,
                'reviewed_by' => Auth::guard('admins')->id(),
                'reviewed_at' => now(),
            ]);

            $notification = new CancellationRequestApprovedNotification($cancellationRequest, $refundAmount);
            $fcmTitleKey = 'responses.Cancellation Request Approved';
            $fcmBodyKey = 'responses.Your cancellation request has been approved';
            $fcmNotificationTypeData = [
                'type' => 'cancellation_request_approved',
                'order_id' => $order->id,
                'cancellation_request_id' => $cancellationRequest->id,
            ];
            dispatch(new SendNotificationJob(collect([$cancellationRequest->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.cancellation request approved successfully'),
                'cancellation_request' => $cancellationRequest->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function reject(Request $request, $request_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-order-cancellation-requests')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'admin_notes' => ['nullable', 'string'],
        ]);

        $cancellationRequest = OrderCancellationRequest::findOrFail($request_id);

        if ($cancellationRequest->status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => __('responses.cancellation request is not pending'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            $cancellationRequest->update([
                'status' => 'rejected',
                'admin_notes' => $request->admin_notes,
                'reviewed_by' => Auth::guard('admins')->id(),
                'reviewed_at' => now(),
            ]);

            $notification = new CancellationRequestRejectedNotification($cancellationRequest);
            $fcmTitleKey = 'responses.Cancellation Request Rejected';
            $fcmBodyKey = 'responses.Your cancellation request has been rejected';
            $fcmNotificationTypeData = [
                'type' => 'cancellation_request_rejected',
                'order_id' => $cancellationRequest->order_id,
                'cancellation_request_id' => $cancellationRequest->id,
            ];
            dispatch(new SendNotificationJob(collect([$cancellationRequest->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.cancellation request rejected successfully'),
                'cancellation_request' => $cancellationRequest->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }
}
