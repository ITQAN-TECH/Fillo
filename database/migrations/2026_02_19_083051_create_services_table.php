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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('sub_category_id')->constrained('sub_categories');
            $table->foreignId('service_provider_id')->constrained('service_providers');
            $table->longText('ar_name');
            $table->longText('en_name');
            $table->longText('ar_description');
            $table->longText('en_description');
            $table->float('service_provider_price');
            $table->float('sale_price');
            $table->float('profit_amount')->default(0);
            $table->integer('duration_time_minutes')->nullable();
            $table->decimal('average_rate', 2, 1)->default(5.0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
