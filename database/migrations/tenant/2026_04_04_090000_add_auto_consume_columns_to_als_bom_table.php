<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('als_bom', function (Blueprint $table) {
            $table->string('manufacturing_mode', 30)->default('pre_produce')->after('use_case');
            $table->string('auto_consume_trigger', 30)->default('invoice')->after('manufacturing_mode');
            $table->index(['manufacturing_mode', 'auto_consume_trigger'], 'als_bom_mode_trigger_index');
        });
    }

    public function down(): void
    {
        Schema::table('als_bom', function (Blueprint $table) {
            $table->dropIndex('als_bom_mode_trigger_index');
            $table->dropColumn(['manufacturing_mode', 'auto_consume_trigger']);
        });
    }
};
