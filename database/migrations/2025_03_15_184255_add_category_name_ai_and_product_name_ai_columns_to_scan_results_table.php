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
        Schema::table('scan_results', function (Blueprint $table) {
            $table->string('category_name_ai')->nullable();
            $table->string('product_name_ai')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scan_results', function (Blueprint $table) {
            $table->dropColumn('category_name_ai');
            $table->dropColumn('product_name_ai');
        });
    }
};
