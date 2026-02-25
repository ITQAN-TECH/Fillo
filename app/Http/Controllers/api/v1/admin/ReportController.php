<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        $report = [];
        $report['total_customers_count'] = Customer::count();
        $report['total_active_customers_count'] = Customer::where('status', true)->count();

        // Revenue Statistics
        $report['revenue'] = $this->getRevenueStatistics();

        // Revenue by Payment Method
        $report['revenue_by_payment_method'] = $this->getRevenueByPaymentMethod();

        // Last 3 Invoices
        $report['last_invoices'] = Payment::with(['order.customer', 'booking.customer'])
            ->where('status', 'completed')
            ->latest()
            ->take(3)
            ->get();

        // Order Statistics
        $report['order_statistics'] = $this->getOrderStatistics();

        // Service Statistics
        $report['service_statistics'] = $this->getServiceStatistics();

        // Top 4 Services
        $report['top_services'] = $this->getTopServices();

        // Top 4 Products
        $report['top_products'] = $this->getTopProducts();

        return response()->json([
            'success' => true,
            'message' => __('responses.all reports'),
            'report' => $report,
        ]);
    }

    private function getRevenueStatistics()
    {
        $revenue = [];

        // Daily Revenue
        $revenue['daily'] = $this->calculateRevenue('day', 1);

        // Weekly Revenue
        $revenue['weekly'] = $this->calculateRevenue('week', 1);

        // Monthly Revenue
        $revenue['monthly'] = $this->calculateRevenue('month', 1);

        // Yearly Revenue
        $revenue['yearly'] = $this->calculateRevenue('year', 1);

        return $revenue;
    }

    private function calculateRevenue($period, $offset)
    {
        $currentStart = $this->getPeriodStart($period, 0);
        $currentEnd = $this->getPeriodEnd($period, 0);
        $previousStart = $this->getPeriodStart($period, $offset);
        $previousEnd = $this->getPeriodEnd($period, $offset);

        // Current Period Revenue
        $currentOrdersRevenue = Payment::whereNotNull('order_id')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->sum('amount');

        $currentBookingsRevenue = Payment::whereNotNull('booking_id')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$currentStart, $currentEnd])
            ->sum('amount');

        $currentTotalRevenue = $currentOrdersRevenue + $currentBookingsRevenue;

        // Previous Period Revenue
        $previousOrdersRevenue = Payment::whereNotNull('order_id')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('amount');

        $previousBookingsRevenue = Payment::whereNotNull('booking_id')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('amount');

        $previousTotalRevenue = $previousOrdersRevenue + $previousBookingsRevenue;

        // Calculate Improvement Percentage
        $totalImprovement = $this->calculateImprovement($currentTotalRevenue, $previousTotalRevenue);
        $ordersImprovement = $this->calculateImprovement($currentOrdersRevenue, $previousOrdersRevenue);
        $bookingsImprovement = $this->calculateImprovement($currentBookingsRevenue, $previousBookingsRevenue);

        return [
            'total_revenue' => round($currentTotalRevenue, 2),
            'orders_revenue' => round($currentOrdersRevenue, 2),
            'bookings_revenue' => round($currentBookingsRevenue, 2),
            'total_improvement_percentage' => round($totalImprovement, 2),
            'orders_improvement_percentage' => round($ordersImprovement, 2),
            'bookings_improvement_percentage' => round($bookingsImprovement, 2),
            'period_start' => $currentStart->format('Y-m-d H:i:s'),
            'period_end' => $currentEnd->format('Y-m-d H:i:s'),
        ];
    }

    private function getPeriodStart($period, $offset)
    {
        switch ($period) {
            case 'day':
                return Carbon::today()->subDays($offset)->startOfDay();
            case 'week':
                return Carbon::now()->startOfWeek()->subWeeks($offset);
            case 'month':
                return Carbon::now()->startOfMonth()->subMonths($offset);
            case 'year':
                return Carbon::now()->startOfYear()->subYears($offset);
        }
    }

    private function getPeriodEnd($period, $offset)
    {
        switch ($period) {
            case 'day':
                return Carbon::today()->subDays($offset)->endOfDay();
            case 'week':
                return Carbon::now()->endOfWeek()->subWeeks($offset);
            case 'month':
                return Carbon::now()->endOfMonth()->subMonths($offset);
            case 'year':
                return Carbon::now()->endOfYear()->subYears($offset);
        }
    }

    private function calculateImprovement($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return (($current - $previous) / $previous) * 100;
    }

    private function getRevenueByPaymentMethod()
    {
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');

        $revenueByMethod = Payment::where('status', 'completed')
            ->select('payment_method', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) use ($totalRevenue) {
                $percentage = $totalRevenue > 0 ? ($item->total_amount / $totalRevenue) * 100 : 0;

                return [
                    'payment_method' => $item->payment_method,
                    'total_amount' => round($item->total_amount, 2),
                    'percentage' => round($percentage, 2),
                ];
            });

        return $revenueByMethod;
    }

    private function getOrderStatistics()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Current Month Statistics
        $currentCompleted = Order::where('order_status', 'completed')
            ->where('created_at', '>=', $currentMonth)
            ->count();

        $currentInTransit = Order::whereIn('order_status', ['shipping', 'delivered'])
            ->where('created_at', '>=', $currentMonth)
            ->count();

        $currentCancelled = Order::where('order_status', 'cancelled')
            ->where('created_at', '>=', $currentMonth)
            ->count();

        $currentDelivered = Order::where('order_status', 'delivered')
            ->where('created_at', '>=', $currentMonth)
            ->count();

        // Last Month Statistics
        $lastCompleted = Order::where('order_status', 'completed')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        $lastInTransit = Order::whereIn('order_status', ['shipping', 'delivered'])
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        $lastCancelled = Order::where('order_status', 'cancelled')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        $lastDelivered = Order::where('order_status', 'delivered')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        return [
            'total_completed' => Order::where('order_status', 'completed')->count(),
            'total_in_transit' => Order::whereIn('order_status', ['shipping', 'delivered'])->count(),
            'total_cancelled' => Order::where('order_status', 'cancelled')->count(),
            'total_delivered' => Order::where('order_status', 'delivered')->count(),
            'completed_improvement_percentage' => round($this->calculateImprovement($currentCompleted, $lastCompleted), 2),
            'in_transit_improvement_percentage' => round($this->calculateImprovement($currentInTransit, $lastInTransit), 2),
            'cancelled_improvement_percentage' => round($this->calculateImprovement($currentCancelled, $lastCancelled), 2),
            'delivered_improvement_percentage' => round($this->calculateImprovement($currentDelivered, $lastDelivered), 2),
        ];
    }

    private function getServiceStatistics()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Current Month Statistics
        $currentCompleted = Booking::where('order_status', 'completed')
            ->where('created_at', '>=', $currentMonth)
            ->count();

        $currentInProgress = Booking::where('order_status', 'confirmed')
            ->where('created_at', '>=', $currentMonth)
            ->count();

        $currentCancelled = Booking::where('order_status', 'cancelled')
            ->where('created_at', '>=', $currentMonth)
            ->count();

        // Last Month Statistics
        $lastCompleted = Booking::where('order_status', 'completed')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        $lastInProgress = Booking::where('order_status', 'confirmed')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        $lastCancelled = Booking::where('order_status', 'cancelled')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        return [
            'total_published_services' => Service::where('status', true)->count(),
            'total_completed_bookings' => Booking::where('order_status', 'completed')->count(),
            'total_in_progress_bookings' => Booking::where('order_status', 'confirmed')->count(),
            'total_cancelled_bookings' => Booking::where('order_status', 'cancelled')->count(),
            'completed_improvement_percentage' => round($this->calculateImprovement($currentCompleted, $lastCompleted), 2),
            'in_progress_improvement_percentage' => round($this->calculateImprovement($currentInProgress, $lastInProgress), 2),
            'cancelled_improvement_percentage' => round($this->calculateImprovement($currentCancelled, $lastCancelled), 2),
        ];
    }

    private function getTopServices()
    {
        $topServiceIds = DB::table('bookings')
            ->select('service_id', DB::raw('COUNT(*) as bookings_count'))
            ->groupBy('service_id')
            ->orderByDesc('bookings_count')
            ->take(4)
            ->pluck('service_id');

        return Service::whereIn('id', $topServiceIds)
            ->withCount('bookings')
            ->orderByDesc('bookings_count')
            ->get();
    }

    private function getTopProducts()
    {
        $topProducts = DB::table('order_items')
            ->select('product_id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.order_status', 'completed')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(4)
            ->get();

        $productIds = $topProducts->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get();

        return $products->map(function ($product) use ($topProducts) {
            $soldData = $topProducts->firstWhere('product_id', $product->id);
            $product->total_sold = $soldData ? $soldData->total_sold : 0;

            return $product;
        })->sortByDesc('total_sold')->values();
    }
}
