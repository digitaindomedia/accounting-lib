<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::create('als_contents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title',254);
            $table->string('meta_key', 256);
            $table->longText('data');
            $table->foreignId('created_by')->unsigned();
            $table->foreignId('updated_by')->unsigned();
            $table->timestamps();
            $table->integer('is_default');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_contents');
    }
};
