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
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable'); // tokenable_id, tokenable_type (polymorphic)
            $table->string('token');
            $table->string('device_id')->nullable(); // لتحديد الجهاز
            $table->string('language')->nullable(); // لتحديد اللغة
            $table->timestamps();

            $table->index(['tokenable_id', 'tokenable_type']);
            $table->unique(['tokenable_id', 'tokenable_type', 'token']); // منع تكرار نفس الرمز لنفس المستخدم
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
