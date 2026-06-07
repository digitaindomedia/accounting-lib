<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('als_purchase_invoice_dp', function (Blueprint $table) {
            $table->double('nominal')->default(0)->after('dp_id');
        });

        DB::statement("
            UPDATE als_purchase_invoice_dp invoice_dp
            INNER JOIN als_purchase_downpayment dp ON dp.id = invoice_dp.dp_id
            SET invoice_dp.nominal = dp.nominal
            WHERE invoice_dp.nominal = 0
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('als_purchase_invoice_dp', function (Blueprint $table) {
            $table->dropColumn('nominal');
        });
    }
};
