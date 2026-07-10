<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MyFatoorah's "PaymentId" (long, e.g. "07076947299365024474") is the
     * only key accepted by MakeRefund (KeyType=PaymentId). It is a different
     * value from "TransactionId" (short, e.g. "296637"), which is what
     * `transaction_id` stores for display/search purposes. Refunding with
     * transaction_id fails with "No transaction exist matching this Key!".
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('mf_payment_id')->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('mf_payment_id');
        });
    }
};
