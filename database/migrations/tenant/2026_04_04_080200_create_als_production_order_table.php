<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('als_production_order', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no', 100)->unique();
            $table->date('production_date');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('bom_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('output_unit_id');
            $table->double('planned_qty')->default(0);
            $table->double('actual_qty')->default(0);
            $table->string('status_production', 30)->default('draft');
            $table->string('source_type', 50)->default('manual');
            $table->unsignedBigInteger('source_id')->default(0);
            $table->unsignedBigInteger('coa_id')->default(0);
            $table->text('note')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('created_at')->default(now());
            $table->unsignedBigInteger('created_by');
            $table->dateTime('updated_at')->default(now());
            $table->unsignedBigInteger('updated_by');

            $table->foreign('warehouse_id')->references('id')->on('als_warehouse')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('bom_id')->references('id')->on('als_bom')->onUpdate('cascade')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('als_product')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('output_unit_id')->references('id')->on('als_unit')->onUpdate('cascade')->onDelete('restrict');
            $table->index(['production_date', 'warehouse_id']);
            $table->index(['product_id', 'status_production']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('als_production_order');
    }
};
