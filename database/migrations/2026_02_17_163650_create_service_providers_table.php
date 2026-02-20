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
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries');
            $table->foreignId('city_id')->constrained('cities');
            $table->string('name');
            $table->string('store_name')->nullable();
            $table->enum('type', ['individual', 'company', 'store']);
            $table->string('phone');
            $table->string('email');
            $table->string('image')->nullable();
            $table->longText('full_address');
            $table->string('specialization')->nullable();
            $table->time('working_hours_start')->default('09:00:00');
            $table->time('working_hours_end')->default('18:00:00');
            $table->integer('daily_orders_count')->default(5);
            $table->string('id_file')->nullable();
            $table->string('commercial_id_file')->nullable();
            $table->string('service_practice_certificate_file')->nullable();
            $table->decimal('average_rate', 2, 1)->default(5.0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};
