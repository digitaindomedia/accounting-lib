<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsTaxTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_tax', function (Blueprint $table) {
            $table->id();
            $table->string('tax_name',254);
            $table->string('tax_description',254)->nullable();
            $table->string('tax_periode',60)->nullable();
            $table->double('tax_percentage')->default(0);
            $table->string('tax_sign',50)->nullable();
            $table->string('tax_type',50)->nullable();
            $table->unsignedBigInteger('is_dpp_nilai_Lain')->default(0);
            $table->unsignedBigInteger('purchase_coa_id')->default(0);
            $table->unsignedBigInteger('sales_coa_id')->default(0);
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_tax');
    }
}
