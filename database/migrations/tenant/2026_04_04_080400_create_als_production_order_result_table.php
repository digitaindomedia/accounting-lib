<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('als_production_order_result', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id');
            $table->double('qty_good')->default(0);
            $table->double('qty_waste')->default(0);
            $table->double('hpp')->default(0);
            $table->double('subtotal')->default(0);
            $table->string('result_role', 30)->default('main');
            $table->text('note')->nullable();

            $table->foreign('production_order_id')->references('id')->on('als_production_order')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('unit_id')->references('id')->on('als_unit')->onUpdate('cascade')->onDelete('restrict');
            $table->index(['production_order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('als_production_order_result');
    }
};
