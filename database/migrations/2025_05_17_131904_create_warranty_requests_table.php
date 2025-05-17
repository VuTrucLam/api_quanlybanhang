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
        Schema::create('warranty_requests', function (Blueprint $table) {
            $table->id(); // request_id
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('customer_id');
            $table->timestamp('received_date')->nullable();
            $table->text('issue_description')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warranty_requests');
    }
};
