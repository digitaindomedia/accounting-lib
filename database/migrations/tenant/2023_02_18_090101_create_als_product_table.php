<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_product', function (Blueprint $table) {
            $table->id();
            $table->string('item_name',254);
            $table->string('item_code',254);
            $table->double('selling_price')->default(0);
            $table->string('item_photo',254)->default('NULL');
            $table->text('descriptions')->nullable();
            $table->string('item_status',30)->default('AKTIF');
            $table->integer('status_price')->default(0);
            $table->bigInteger('unit_id')->unsigned();
            $table->string('type_price', 30)->nullable();
            $table->string('is_has_tax')->default('0');
            $table->double('min_stock')->default(0);
            $table->string('product_type',30)->default('item')->comment('pilihan hanya item atau service');
            $table->bigInteger('coa_id')->unsigned()->default(0)->comment('jika tipe produk item maka buat coa sediaan dan jika jasa buat coa pendapatan');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
            $table->bigInteger('coa_biaya_id')->unsigned()->default(0);
            $table->index(['unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_product');
    }
}
