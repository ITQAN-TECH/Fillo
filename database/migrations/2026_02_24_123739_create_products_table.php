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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('sub_category_id')->constrained('sub_categories');
            $table->string('ar_name');
            $table->string('en_name');
            $table->text('ar_description')->nullable();
            $table->text('en_description')->nullable();
            $table->text('ar_small_description')->nullable();
            $table->text('en_small_description')->nullable();
            $table->string('sku')->unique();
            $table->decimal('sale_price', 10, 2);
            $table->boolean('status')->default(true);
            $table->decimal('average_rate', 2, 1)->default(5.0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
