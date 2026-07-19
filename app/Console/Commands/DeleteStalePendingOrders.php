<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for abandoned checkouts: permanently deletes orders/bookings
 * whose payment never completed within N days. Mirrors MyFatoorah's own
 * invoice expiration window (3 days by default on this account).
 *
 * Deletion (not cancellation) is intentional here: unpaid/pending
 * orders/bookings are never shown to the customer or the admin anywhere in
 * the app, so there's nothing worth keeping a "cancelled" record of — they
 * were never really orders from the user's perspective, just abandoned
 * checkout attempts. Nothing irreversible (stock, cart) was ever touched
 * for them by design, so deleting is safe.
 */
class DeleteStalePendingOrders extends Command
{
    protected $signature = 'payments:delete-stale-pending {--days=3 : Age in days after which an unpaid order/booking is considered abandoned}';

    protected $description = 'Delete orders/bookings whose payment was never completed within N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $staleIds = Payment::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->pluck('id');

        $deletedOrders = 0;
        $deletedBookings = 0;

        foreach ($staleIds as $paymentId) {
            DB::beginTransaction();
            try {
                $payment = Payment::where('id', $paymentId)->lockForUpdate()->first();

                // Guard against a race with the webhook, which may have
                // confirmed/failed this exact payment between the query
                // above and now.
                if (! $payment || $payment->status !== 'pending') {
                    DB::commit();

                    continue;
                }

                if ($payment->order_id && $payment->order && $payment->order->order_status === 'pending') {
                    $order = $payment->order;
                    $orderId = $order->id;

                    // order_items has no cascadeOnDelete — must clear it
                    // first or the order delete below fails on the FK.
                    $order->items()->delete();
                    $payment->delete();
                    $order->delete();

                    $deletedOrders++;
                    Log::info('payments:cancel-stale-pending — deleted abandoned order', ['order_id' => $orderId]);
                } elseif ($payment->booking_id && $payment->booking && $payment->booking->order_status === 'pending') {
                    $booking = $payment->booking;
                    $bookingId = $booking->id;

                    $payment->delete();
                    $booking->delete();

                    $deletedBookings++;
                    Log::info('payments:cancel-stale-pending — deleted abandoned booking', ['booking_id' => $bookingId]);
                } else {
                    // Payment's order/booking is gone or no longer pending — leave it alone.
                    DB::commit();

                    continue;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('payments:cancel-stale-pending — failed to delete', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Deleted {$deletedOrders} stale order(s) and {$deletedBookings} stale booking(s).");

        return self::SUCCESS;
    }
}
