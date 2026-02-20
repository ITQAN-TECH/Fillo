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
        Schema::create('order_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->foreignId('customer_address_id')->contrained('customer_addresses');
            $table->string('coupon_code')->nullable();
            $table->float('service_provider_price');
            $table->float('sale_price');
            $table->float('profit_amount')->default(0);
            $table->float('discount_percentage')->default(0);
            $table->float('discount_amount')->default(0);
            $table->float('service_provider_price_after_discount')->default(0);
            $table->float('sale_price_after_discount')->default(0);
            $table->float('profit_amount_after_discount')->default(0);
            $table->dateTime('order_date');
            $table->dateTime('delivery_date')->nullable();
            $table->enum('order_status', ['pending', 'confirmed', 'completed', 'cancelled', 'shipped', 'delivered'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_services');
    }
};
