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
        Schema::create('export_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('export_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->foreign('export_id')->references('id')->on('exports')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_details');
    }
};
