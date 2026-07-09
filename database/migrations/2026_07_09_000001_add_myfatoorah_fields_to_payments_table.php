<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // MyFatoorah InvoiceId — unique across all payments for idempotency
            $table->string('invoice_id')->nullable()->unique()->after('transaction_id');

            // Distinguish SDK-native vs hosted-web-page payment flows
            $table->enum('payment_source', ['sdk', 'web'])->nullable()->default('sdk')->after('payment_method');

            // Ensure payment_response can hold the full JSON (TEXT already in migration; add comment)
            // transaction_id is already varchar — kept as-is (MyFatoorah sends string IDs)
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['invoice_id']);
            $table->dropColumn(['invoice_id', 'payment_source']);
        });
    }
};
