<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-payments')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $request->validate([
            'search' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|in:completed,failed,refunded,all',
            'payment_method' => 'sometimes|nullable|string',
            'type' => 'sometimes|nullable|in:order,booking,all',
        ]);

        $payments = Payment::when($request->has('status') && $request->status != 'all', function ($query) use ($request) {
            $query->where('status', $request->status);
        })->when($request->has('payment_method') && $request->payment_method != 'all', function ($query) use ($request) {
            $query->where('payment_method', $request->payment_method);
        })->when($request->has('type') && $request->type != 'all', function ($query) use ($request) {
            if ($request->type == 'order') {
                $query->whereNotNull('order_id');
            } elseif ($request->type == 'booking') {
                $query->whereNotNull('booking_id');
            }
        })->when($request->has('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('transaction_id', 'like', '%'.$search.'%')
                    ->orWhere('amount', 'like', '%'.$search.'%')
                    ->orWhereHas('order', function ($query) use ($search) {
                        $query->where('order_number', 'like', '%'.$search.'%')
                            ->orWhereHas('customer', function ($query) use ($search) {
                                $query->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('email', 'like', '%'.$search.'%')
                                    ->orWhere('phone', 'like', '%'.$search.'%');
                            });
                    })->orWhereHas('booking', function ($query) use ($search) {
                        $query->whereHas('customer', function ($query) use ($search) {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%')
                                ->orWhere('phone', 'like', '%'.$search.'%');
                        });
                    });
            });
        })->latest()->paginate();

        $report = [];
        $report['total_payments'] = Payment::count();
        $report['completed_payments'] = Payment::where('status', 'completed')->count();
        $report['failed_payments'] = Payment::where('status', 'failed')->count();
        $report['refunded_payments'] = Payment::where('status', 'refunded')->count();
        $report['total_amount'] = Payment::where('status', 'completed')->sum('amount');
        $report['total_refunded_amount'] = Payment::where('status', 'refunded')->sum('refunded_amount');

        return response()->json([
            'success' => true,
            'message' => __('responses.all payments'),
            'report' => $report,
            'payments' => $payments,
        ]);
    }

    public function show($payment_id)
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-payments')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $payment = Payment::findOrFail($payment_id);

        return response()->json([
            'success' => true,
            'message' => __('responses.payment'),
            'payment' => $payment,
        ]);
    }

    public function statistics()
    {
        if (! Auth::guard('admins')->user()->hasPermission('show-payments')) {
            return response()->json([
                'success' => false,
                'message' => __('responses.forbidden'),
            ], 403);
        }

        $statistics = [];

        // Overall statistics
        $statistics['total_payments'] = Payment::count();
        $statistics['total_amount'] = Payment::where('status', 'completed')->sum('amount');
        $statistics['total_refunded_amount'] = Payment::where('status', 'refunded')->sum('refunded_amount');

        // By status
        $statistics['by_status'] = [
            // 'pending' => Payment::where('status', 'pending')->count(),
            'completed' => Payment::where('status', 'completed')->count(),
            'failed' => Payment::where('status', 'failed')->count(),
            'refunded' => Payment::where('status', 'refunded')->count(),
        ];

        // By type
        $statistics['by_type'] = [
            'orders' => Payment::whereNotNull('order_id')->count(),
            'bookings' => Payment::whereNotNull('booking_id')->count(),
        ];

        // By payment method
        $statistics['by_payment_method'] = Payment::selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
            ->where('status', 'completed')
            ->groupBy('payment_method')
            ->get();

        // Recent transactions
        $statistics['recent_payments'] = Payment::latest()->take(10)->get();

        return response()->json([
            'success' => true,
            'message' => __('responses.payment statistics'),
            'statistics' => $statistics,
        ]);
    }
}
