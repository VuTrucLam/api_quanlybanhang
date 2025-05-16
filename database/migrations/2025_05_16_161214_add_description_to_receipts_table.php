<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('description')->nullable()->after('category_id');
        });
    }

    public function down()
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
