<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('als_production_order', function (Blueprint $table) {
            if (!Schema::hasColumn('als_production_order', 'hpp_allocation_method')) {
                $table->string('hpp_allocation_method', 20)->default('qty')->after('actual_qty');
            }
        });
    }

    public function down(): void
    {
        Schema::table('als_production_order', function (Blueprint $table) {
            if (Schema::hasColumn('als_production_order', 'hpp_allocation_method')) {
                $table->dropColumn('hpp_allocation_method');
            }
        });
    }
};
