<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('als_bom', function (Blueprint $table) {
            if (!Schema::hasColumn('als_bom', 'manufacturing_mode')) {
                $table->string('manufacturing_mode', 30)->default('pre_produce')->after('use_case');
            }

            if (!Schema::hasColumn('als_bom', 'auto_consume_trigger')) {
                $table->string('auto_consume_trigger', 30)->default('invoice')->after('manufacturing_mode');
            }

            if (!Schema::hasIndex('als_bom', 'als_bom_mode_trigger_index')) {
                $table->index(['manufacturing_mode', 'auto_consume_trigger'], 'als_bom_mode_trigger_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('als_bom', function (Blueprint $table) {
            if (Schema::hasIndex('als_bom', 'als_bom_mode_trigger_index')) {
                $table->dropIndex('als_bom_mode_trigger_index');
            }

            $columns = array_filter(['manufacturing_mode', 'auto_consume_trigger'], function ($column) {
                return Schema::hasColumn('als_bom', $column);
            });

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
