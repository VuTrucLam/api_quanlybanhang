<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_requests', function (Blueprint $table) {
            $table->timestamp('returned_date')->nullable()->after('sent_date');
            $table->text('resolution')->nullable()->after('returned_date');
        });
    }

    public function down(): void
    {
        Schema::table('warranty_requests', function (Blueprint $table) {
            $table->dropColumn('returned_date');
            $table->dropColumn('resolution');
        });
    }
};