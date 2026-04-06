<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
class CreateAlsSalesDeliveryProductItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_delivery_product_items', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('delivery_product_id')->unsigned();
            $table->bigInteger('product_id')->unsigned();
            $table->bigInteger('identity_item_id')->unsigned();
            // identity bebas: batch / serial / no rangka / dll
            $table->string('identity_value', 100);
            $table->double('qty');

            $table->timestamps();

            $table->foreign('delivery_product_id')
                ->references('id')
                ->on('als_sales_delivery_product')
                ->onDelete('cascade');

            $table->index([
                'product_id',
                'identity_item_id'
            ],'als_sdip_prod_identity_ind');
        });
    }

    public function down()
    {
        Schema::dropIfExists('als_sales_delivery_product_items');
    }
}
