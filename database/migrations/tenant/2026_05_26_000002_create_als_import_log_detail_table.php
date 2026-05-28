<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('als_import_log_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_log_id')->index();
            $table->unsignedBigInteger('transaksi_id')->index();

            $table->unique(['import_log_id', 'transaksi_id'], 'import_log_detail_unique');
            $table->foreign('import_log_id')
                ->references('id')
                ->on('als_import_log')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('als_import_log_detail');
    }
};
