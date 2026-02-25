<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->foreignId('country_id')->constrained('countries');
            $table->foreignId('city_id')->constrained('cities');
            $table->string('full_address');
            $table->string('phone');
            $table->string('national_address_short_number');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->string('order_number')->unique();
            $table->float('subtotal_price');
            $table->float('discount_percentage')->default(0);
            $table->float('discount_amount')->default(0);
            $table->float('subtotal_price_after_discount');
            $table->float('shipping_fee')->default(0);
            $table->float('total_price');
            $table->enum('order_status', ['pending', 'confirmed', 'shipping', 'delivered', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->enum('cancellation_reason', ['administrative', 'customer_not_received'])->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
