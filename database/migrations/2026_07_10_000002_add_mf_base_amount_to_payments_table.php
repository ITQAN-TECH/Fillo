<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MakeRefund's "Amount" is validated against the invoice's value in the
     * MERCHANT ACCOUNT's base currency (e.g. KWD on this Kuwait-provisioned
     * test account) — NOT the SAR amount we display to customers and store
     * in `amount`. Passing the SAR amount causes MyFatoorah to reject with
     * "Maximum amount to be refunded is X" (X being the base-currency value).
     * We store that base-currency value here at webhook-confirmation time so
     * refunds always request the correct, refundable amount.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('mf_base_amount', 12, 3)->nullable()->after('mf_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('mf_base_amount');
        });
    }
};
