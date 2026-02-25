<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Booking;
use App\Notifications\admins\BookingConfirmedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-bookings')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $request->validate([
            'search' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|in:pending,confirmed,completed,cancelled,all',
        ]);
        $bookings = Booking::when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
            $query->where('order_status', $request->status);
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->whereHas('service', function ($query) use ($search) {
                    $query->where('ar_name', 'like', '%'.$search.'%')
                        ->orWhere('en_name', 'like', '%'.$search.'%');
                })->orWhereHas('customer', function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                })->orWhereHas('coupon', function ($query) use ($search) {
                    $query->where('code', 'like', '%'.$search.'%');
                })->orWhereHas('customerAddress', function ($query) use ($search) {
                    $query->where('address_title', 'like', '%'.$search.'%')
                        ->orWhere('full_address', 'like', '%'.$search.'%');
                })->orWhereHas('payment', function ($query) use ($search) {
                    $query->where('payment_method', 'like', '%'.$search.'%')
                        ->orWhere('transaction_id', 'like', '%'.$search.'%')
                        ->orWhere('amount', 'like', '%'.$search.'%');
                });
            });
        })->latest()->paginate();
        $report = [];
        $report['bookings_count'] = Booking::count();
        $report['pending_bookings_count'] = Booking::where('order_status', 'pending')->count();
        $report['confirmed_bookings_count'] = Booking::where('order_status', 'confirmed')->count();
        $report['completed_bookings_count'] = Booking::where('order_status', 'completed')->count();
        $report['cancelled_bookings_count'] = Booking::where('order_status', 'cancelled')->count();

        return response()->json([
            'success' => true,
            'message' => __('responses.all bookings'),
            'report' => $report,
            'bookings' => $bookings,
        ]);
    }

    public function show($booking_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-bookings')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $booking = Booking::findOrFail($booking_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.booking'),
            'booking' => $booking,
        ]);
    }

    public function confirmBooking($booking_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-bookings')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $booking = Booking::findOrFail($booking_id);
        if ($booking->order_status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => __('responses.booking is not pending'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $booking->update([
                'order_status' => 'confirmed',
            ]);
            $notification = new BookingConfirmedNotification($booking);
            $fcmTitleKey = 'responses.Booking Confirmed';
            $fcmBodyKey = 'responses.Your booking has been confirmed';
            $fcmNotificationTypeData = [
                'type' => 'booking_confirmed',
                'booking_id' => $booking->id,
            ];
            dispatch(new SendNotificationJob(collect([$booking->customer]), $notification, $fcmTitleKey, $fcmBodyKey, true, [], $fcmNotificationTypeData));
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.booking confirmed successfully'),
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function completeBooking($booking_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-bookings')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $booking = Booking::findOrFail($booking_id);
        if ($booking->order_status != 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => __('responses.booking is not confirmed'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $booking->update([
                'order_status' => 'completed',
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.booking completed successfully'),
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('responses.error happened'),
            ], 400);
        }
    }

    public function cancelBooking($booking_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('edit-bookings')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }
        $booking = Booking::findOrFail($booking_id);
        if ($booking->order_status != 'pending') {
            return response()->json([
                'success' => false,
                'message' => __('responses.booking is not pending'),
            ], 400);
        }
        try {
            DB::beginTransaction();
            $booking->update([
                'order_status' => 'cancelled',
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('responses.booking cancelled successfully'),
                'booking' => $booking,
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
