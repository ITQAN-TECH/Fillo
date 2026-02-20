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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->longText('ar_about_us');
            $table->longText('en_about_us');
            $table->longText('ar_terms_and_conditions');
            $table->longText('en_terms_and_conditions');
            $table->longText('ar_privacy_policy');
            $table->longText('en_privacy_policy');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
