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
        Schema::create('als_user_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('filename', 254);
            $table->string('path', 254);
            $table->double('size');
            $table->timestamps();
            $table->string('tenant_id')->nullable();
            $table->index(['user_id', 'path'],'als_user_files_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_user_files');
    }
};
