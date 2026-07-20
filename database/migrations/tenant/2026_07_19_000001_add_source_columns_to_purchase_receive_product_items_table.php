<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('als_purchase_receive_product_items')) {
            return;
        }

        $this->dropReceiveProductForeignKey();

        Schema::table('als_purchase_receive_product_items', function (Blueprint $table) {
            $table->bigInteger('receive_product_id')->unsigned()->nullable()->change();

            if (!Schema::hasColumn('als_purchase_receive_product_items', 'source_type')) {
                $table->string('source_type', 30)->default('purchase_receive')->after('warehouse_id');
            }

            if (!Schema::hasColumn('als_purchase_receive_product_items', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            if (!Schema::hasColumn('als_purchase_receive_product_items', 'source_product_id')) {
                $table->unsignedBigInteger('source_product_id')->nullable()->after('source_id');
            }
        });

        DB::table('als_purchase_receive_product_items')
            ->whereNull('source_type')
            ->orWhere('source_type', '')
            ->update(['source_type' => 'purchase_receive']);

        Schema::table('als_purchase_receive_product_items', function (Blueprint $table) {
            $table->foreign('receive_product_id')
                ->references('id')
                ->on('als_purchase_receive_product')
                ->onDelete('cascade');

            $table->index(['source_type', 'source_id'], 'als_prpi_source_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('als_purchase_receive_product_items')) {
            return;
        }

        $this->dropReceiveProductForeignKey();

        Schema::table('als_purchase_receive_product_items', function (Blueprint $table) {
            if (Schema::hasColumn('als_purchase_receive_product_items', 'source_type')) {
                $table->dropIndex('als_prpi_source_idx');
                $table->dropColumn(['source_type', 'source_id', 'source_product_id']);
            }
        });

        if (!DB::table('als_purchase_receive_product_items')->whereNull('receive_product_id')->exists()) {
            Schema::table('als_purchase_receive_product_items', function (Blueprint $table) {
                $table->bigInteger('receive_product_id')->unsigned()->nullable(false)->change();
            });
        }

        Schema::table('als_purchase_receive_product_items', function (Blueprint $table) {
            $table->foreign('receive_product_id')
                ->references('id')
                ->on('als_purchase_receive_product')
                ->onDelete('cascade');
        });
    }

    private function dropReceiveProductForeignKey(): void
    {
        $database = DB::getDatabaseName();
        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'als_purchase_receive_product_items')
            ->where('COLUMN_NAME', 'receive_product_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if (!empty($constraint)) {
            Schema::table('als_purchase_receive_product_items', function (Blueprint $table) use ($constraint) {
                $table->dropForeign($constraint);
            });
        }
    }
};
