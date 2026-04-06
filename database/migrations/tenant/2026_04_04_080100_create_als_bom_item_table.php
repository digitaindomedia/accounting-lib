<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('als_bom_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bom_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('unit_id');
            $table->double('qty');
            $table->double('waste_percentage')->default(0);
            $table->string('item_role', 30)->default('material');
            $table->boolean('is_optional')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('note')->nullable();

            $table->foreign('bom_id')->references('id')->on('als_bom')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('unit_id')->references('id')->on('als_unit')->onUpdate('cascade')->onDelete('restrict');
            $table->index(['bom_id', 'sort_order']);
            $table->index(['product_id', 'item_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('als_bom_item');
    }
};
