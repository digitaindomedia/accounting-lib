<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateAlsPurchaseReceiveProductItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_purchase_receive_product_items', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('receive_product_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('warehouse_id')->unsigned();

            // identity bebas: batch / serial / no rangka / dll
            $table->string('identity_value', 100);
            $table->date('expired_date')->nullable();

            $table->double('qty');        // qty awal
            $table->double('qty_left');   // sisa qty

            $table->enum('status', ['open','close'])->default('open');

            $table->timestamps();

            // satu identity tidak boleh dobel per produk
            $table->unique(['product_id', 'identity_value'], 'als_prpi_prod_identity_uq');

            $table->foreign('receive_product_id')
                ->references('id')
                ->on('als_purchase_receive_product')
                ->onDelete('cascade');

            $table->index([
                'product_id',
                'warehouse_id',
                'status'
            ],'als_prpi_prod_identity_ind');
        });
    }

    public function down()
    {
        Schema::dropIfExists('als_purchase_receive_product_items');
    }
}
