<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('als_warehouse_mutation', function (Blueprint $table) {
            if (!Schema::hasColumn('als_warehouse_mutation', 'quotation_id')) {
                $table->unsignedBigInteger('quotation_id')->default(0)->after('mutation_out_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('als_warehouse_mutation', function (Blueprint $table) {
            if (Schema::hasColumn('als_warehouse_mutation', 'quotation_id')) {
                $table->dropColumn('quotation_id');
            }
        });
    }
};
