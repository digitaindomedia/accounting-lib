<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('als_bom', function (Blueprint $table) {
            $table->id();
            $table->string('bom_code', 100)->unique();
            $table->string('bom_name', 255);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('output_unit_id');
            $table->double('output_qty')->default(1);
            $table->string('bom_version', 50)->default('1.0');
            $table->string('use_case', 50)->default('general');
            $table->string('status', 30)->default('active');
            $table->double('yield_percentage')->default(100);
            $table->text('note')->nullable();
            $table->dateTime('created_at')->default(now());
            $table->unsignedBigInteger('created_by');
            $table->dateTime('updated_at')->default(now());
            $table->unsignedBigInteger('updated_by');

            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('output_unit_id')->references('id')->on('als_unit')->onUpdate('cascade')->onDelete('restrict');
            $table->index(['product_id', 'status']);
            $table->index(['use_case', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('als_bom');
    }
};
