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
        Schema::create('als_jurnal_meta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jurnal_id'); // Equivalent to BIGINT UNSIGNED.
            $table->string('meta_key', 254); // VARCHAR(254).
            $table->mediumText('meta_value')->nullable(); // MEDIUMTEXT with NULL default.

            // Indexes
            $table->index('jurnal_id'); //
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_jurnal_meta');
    }
};
