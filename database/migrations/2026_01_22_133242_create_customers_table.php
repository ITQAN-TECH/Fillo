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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique();
            $table->boolean('is_phone_verified')->default(false);
            $table->string('password');
            $table->boolean('status')->default(true);
            $table->string('otp')->nullable();
            $table->boolean('receive_notifications')->default(true);
            $table->string('image')->nullable();
            $table->string('national_address_short_number')->nullable();
            $table->string('currency')->default('SAR');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
