<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Notifications\customers\OrderCancelledNotification;
use App\Notifications\customers\OrderCompletedNotification;
use App\Notifications\customers\OrderConfirmedNotification;
use App\Notifications\customers\OrderDeliveredNotification;
use App\Notifications\customers\OrderRefundedNotification;
use App\Notifications\customers\OrderRejectedNotification;
use App\Notifications\customers\OrderShippedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'search' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|in:pending,confirmed,shipping,delivered,completed,cancelled,refunded,all',
        ]);

        $orders = Order::when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
            $query->where('order_status', $request->status);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('order_number', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($query) use ($search) {
                        $query->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        })->latest()->paginate();

        $report = [];
        $report['orders_count'] = Order::count();
        $report['pending_orders_count'] = Order::where('order_status', 'pending')->count();
        $report['confirmed_orders_count'] = Order::where('order_status', 'confirmed')->count();
        $report['shipping_orders_count'] = Order::where('order_status', 'shipping')->count();
        $report['delivered_orders_count'] = Order::where('order_status', 'delivered')->count();
        $report['completed_orders_count'] = Order::where('order_status', 'completed')->count();
        $report['cancelled_orders_count'] = Order::where('order_status', 'cancelled')->count();
        $report['refunded_orders_count'] = Order::where('order_status', 'refunded')->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all orders'),
            'report' => $report,
            'orders' => $orders,
        ]);
    }

    public function show($order_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $order = Order::findOrFail($order_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.order'),
            'order' => $order,
        ]);
    }

    public function confirmOrder($order_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $order = Order::findOrFail($order_id);

        if ($order->order_status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => __('responses.order is not pending'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'confirmed',
            ]);

            $notification = new OrderConfirmedNotification($order);
            $fcmTitleKey = 'responses.Order Confirmed';
            $fcmBodyKey = 'responses.Your order has been confirmed';
            $fcmNotificationTypeData = [
                'type' => 'order_confirmed',
                'order_id' => $order->id,
            ];
            dispatch(new SendNotificationJob(collect([$order->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.order confirmed successfully'),
                'order' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function rejectOrder($order_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $order = Order::findOrFail($order_id);

        if ($order->order_status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => __('responses.order is not pending'),
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

            $order->update([
                'order_status' => 'cancelled',
                'cancellation_reason' => 'administrative',
            ]);

            $payment = Payment::where('order_id', $order->id)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'refunded',
                    'refunded_amount' => $order->total_price,
                ]);
            }

            $notification = new OrderRejectedNotification($order);
            $fcmTitleKey = 'responses.Order Rejected';
            $fcmBodyKey = 'responses.Your order has been rejected and refunded';
            $fcmNotificationTypeData = [
                'type' => 'order_rejected',
                'order_id' => $order->id,
            ];
            dispatch(new SendNotificationJob(collect([$order->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.order rejected successfully'),
                'order' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function shipOrder($order_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $order = Order::findOrFail($order_id);

        if ($order->order_status != 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => __('responses.order is not confirmed'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'shipping',
            ]);

            $notification = new OrderShippedNotification($order);
            $fcmTitleKey = 'responses.Order Shipped';
            $fcmBodyKey = 'responses.Your order has been shipped';
            $fcmNotificationTypeData = [
                'type' => 'order_shipped',
                'order_id' => $order->id,
            ];
            dispatch(new SendNotificationJob(collect([$order->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.order shipped successfully'),
                'order' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function deliverOrder($order_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $order = Order::findOrFail($order_id);

        if ($order->order_status != 'shipping') {
            return response()->json([
                'success' => false,
                'message' => __('responses.order is not shipping'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'delivered',
            ]);

            $notification = new OrderDeliveredNotification($order);
            $fcmTitleKey = 'responses.Order Delivered';
            $fcmBodyKey = 'responses.Your order has been delivered';
            $fcmNotificationTypeData = [
                'type' => 'order_delivered',
                'order_id' => $order->id,
            ];
            dispatch(new SendNotificationJob(collect([$order->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.order delivered successfully'),
                'order' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function completeOrder($order_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $order = Order::findOrFail($order_id);

        if ($order->order_status != 'delivered') {
            return response()->json([
                'success' => false,
                'message' => __('responses.order is not delivered'),
            ], 400);
        }

        try {
            DB::beginTransaction();

            $order->update([
                'order_status' => 'completed',
            ]);

            $notification = new OrderCompletedNotification($order);
            $fcmTitleKey = 'responses.Order Completed';
            $fcmBodyKey = 'responses.Your order has been completed';
            $fcmNotificationTypeData = [
                'type' => 'order_completed',
                'order_id' => $order->id,
            ];
            dispatch(new SendNotificationJob(collect([$order->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.order completed successfully'),
                'order' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function cancelOrder(Request $request, $order_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'cancellation_reason' => ['required', 'in:administrative,customer_not_received'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $order = Order::findOrFail($order_id);

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

            $notification = new OrderCancelledNotification($order, $refundAmount);
            $fcmTitleKey = 'responses.Order Cancelled';
            $fcmBodyKey = 'responses.Your order has been cancelled';
            $fcmNotificationTypeData = [
                'type' => 'order_cancelled',
                'order_id' => $order->id,
            ];
            dispatch(new SendNotificationJob(collect([$order->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.order cancelled successfully'),
                'order' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function refundOrder($order_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-orders')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $order = Order::findOrFail($order_id);

        if ($order->order_status != 'completed') {
            return response()->json([
                'success' => false,
                'message' => __('responses.order must be completed to refund'),
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

            $order->update([
                'order_status' => 'refunded',
            ]);

            $payment = Payment::where('order_id', $order->id)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'refunded',
                    'refunded_amount' => $order->total_price,
                ]);
            }

            $notification = new OrderRefundedNotification($order);
            $fcmTitleKey = 'responses.Order Refunded';
            $fcmBodyKey = 'responses.Your order has been refunded';
            $fcmNotificationTypeData = [
                'type' => 'order_refunded',
                'order_id' => $order->id,
            ];
            dispatch(new SendNotificationJob(collect([$order->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.order refunded successfully'),
                'order' => $order->fresh(),
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
