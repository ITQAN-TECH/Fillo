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
        Schema::create('notification_from_admins', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('desc');
            $table->enum('target', ['all', 'specific']);
            $table->enum('type', ['default', 'schedule']);
            $table->dateTime('schedule_at')->nullable();
            $table->json('target_data')->nullable(); // json array of target ids
            $table->boolean('is_sent')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_from_admins');
    }
};
