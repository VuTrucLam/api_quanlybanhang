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
        Schema::create('inventory_check_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_check_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('actual_quantity');
            $table->foreign('inventory_check_id')->references('id')->on('inventory_checks')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_check_details');
    }
};
