<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order::payment() / Booking::payment() are hasOne relations, and several
     * controllers do Payment::where('order_id', ...)->first() — both assume
     * at most one Payment row per order/booking. That's true today only
     * because Payment::create() is called exactly once, at order/booking
     * creation time (verified: no code path ever creates a second Payment
     * for an existing order/booking). Nothing enforced that invariant at the
     * DB level though, so a future bug/feature could silently insert a
     * second row and make hasOne()/first() pick an arbitrary (possibly
     * stale/failed) payment instead of the real one. This makes it
     * impossible: MySQL treats multiple NULLs as distinct under a unique
     * index, so order-payments and booking-payments don't conflict with
     * each other.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique('order_id');
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropUnique(['booking_id']);
        });
    }
};
