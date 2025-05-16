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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('type'); // "receipt" hoặc "payment"
            $table->decimal('amount', 15, 2);
            $table->unsignedBigInteger('category_id'); // Liên kết với revenue_types
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('revenue_types')->onDelete('cascade');
            $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
