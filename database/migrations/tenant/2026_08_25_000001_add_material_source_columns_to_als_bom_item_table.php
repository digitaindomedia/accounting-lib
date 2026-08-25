<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('als_bom_item', function (Blueprint $table) {
            try {
                $table->dropForeign(['product_id']);
            } catch (\Throwable $e) {
                // Foreign key may already be removed on some tenant databases.
            }

            if (!Schema::hasColumn('als_bom_item', 'material_source_type')) {
                $table->string('material_source_type', 20)->default('product')->after('bom_id');
            }

            if (!Schema::hasColumn('als_bom_item', 'source_product_id')) {
                $table->unsignedBigInteger('source_product_id')->nullable()->after('material_source_type');
            }

            if (!Schema::hasColumn('als_bom_item', 'source_category_id')) {
                $table->unsignedBigInteger('source_category_id')->nullable()->after('source_product_id');
            }

            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->index(['material_source_type', 'source_category_id'], 'als_bom_item_source_category_idx');
        });
    }

    public function down(): void
    {
        Schema::table('als_bom_item', function (Blueprint $table) {
            if (Schema::hasColumn('als_bom_item', 'material_source_type')) {
                $table->dropIndex('als_bom_item_source_category_idx');
                $table->dropColumn([
                    'material_source_type',
                    'source_product_id',
                    'source_category_id',
                ]);
            }
        });
    }
};
