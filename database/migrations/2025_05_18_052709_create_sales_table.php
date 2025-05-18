<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales')) { // Kiểm tra xem bảng đã tồn tại chưa
            Schema::create('sales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('shipping_carrier_id');
                $table->decimal('total_amount', 15, 2);
                $table->timestamp('sale_date')->useCurrent();
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('shipping_carrier_id')->references('id')->on('shipping_carriers')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};