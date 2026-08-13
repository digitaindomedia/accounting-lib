<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('als_production_order_result', function (Blueprint $table) {
            if (!Schema::hasColumn('als_production_order_result', 'qty_planned')) {
                $table->double('qty_planned')->default(0)->after('unit_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('als_production_order_result', function (Blueprint $table) {
            if (Schema::hasColumn('als_production_order_result', 'qty_planned')) {
                $table->dropColumn('qty_planned');
            }
        });
    }
};
