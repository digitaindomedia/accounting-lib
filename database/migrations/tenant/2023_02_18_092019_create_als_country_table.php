<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsCountryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_country', function (Blueprint $table) {
            $table->id();
            $table->string('country_name',254);
            $table->string('code1',30);
            $table->string('code2',30);
            $table->string('flag',30);
            $table->integer('is_indonesia')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_country');
    }
}
