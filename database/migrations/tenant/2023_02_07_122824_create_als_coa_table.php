<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsCoaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_coa', function (Blueprint $table) {
            $table->id();
            $table->string('coa_name',245)->nullable()->default('NULL');
            $table->string('coa_code',45)->nullable()->default('NULL');
            $table->text('description')->nullable();
            $table->string('coa_position',45)->nullable()->default('NULL');
            $table->dateTime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('created_by')->default(0);
            $table->dateTime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));
            $table->integer('updated_by')->default(0);
            $table->integer('coa_status')->default(0);
            $table->integer('coa_level');
            $table->integer('coa_parent');
            $table->integer('neraca')->default('0');
            $table->integer('laba_rugi')->default('0');
            $table->string('field_name',250)->nullable()->default('NULL');
            $table->integer('connect_db')->default(0);
            $table->string('coa_category',60);
            $table->string('neraca_type',70)->nullable();
            $table->string('laba_rugi_type',70)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_coa');
    }
}
