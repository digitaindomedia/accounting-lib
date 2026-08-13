<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('als_production_order_material', function (Blueprint $table) {
            if (!Schema::hasColumn('als_production_order_material', 'material_source_type')) {
                $table->string('material_source_type', 20)->default('product')->after('bom_item_id');
            }

            if (!Schema::hasColumn('als_production_order_material', 'source_product_id')) {
                $table->unsignedBigInteger('source_product_id')->nullable()->after('material_source_type');
            }

            if (!Schema::hasColumn('als_production_order_material', 'source_category_id')) {
                $table->unsignedBigInteger('source_category_id')->nullable()->after('source_product_id');
            }

            if (!Schema::hasColumn('als_production_order_material', 'requested_qty_planned')) {
                $table->double('requested_qty_planned')->default(0)->after('qty_actual');
            }

            if (!Schema::hasColumn('als_production_order_material', 'requested_qty_actual')) {
                $table->double('requested_qty_actual')->default(0)->after('requested_qty_planned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('als_production_order_material', function (Blueprint $table) {
            $columns = array_filter([
                'material_source_type',
                'source_product_id',
                'source_category_id',
                'requested_qty_planned',
                'requested_qty_actual',
            ], function ($column) {
                return Schema::hasColumn('als_production_order_material', $column);
            });

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
