<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('als_production_order_result', function (Blueprint $table) {
            if (!Schema::hasColumn('als_production_order_result', 'hpp_allocation_percentage')) {
                $table->double('hpp_allocation_percentage')->default(0)->after('qty_waste');
            }
        });
    }

    public function down(): void
    {
        Schema::table('als_production_order_result', function (Blueprint $table) {
            if (Schema::hasColumn('als_production_order_result', 'hpp_allocation_percentage')) {
                $table->dropColumn('hpp_allocation_percentage');
            }
        });
    }
};
