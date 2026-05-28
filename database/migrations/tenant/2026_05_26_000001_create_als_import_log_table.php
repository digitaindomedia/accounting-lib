<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('als_import_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('import_at')->index();
            $table->integer('user_id')->nullable()->index();
            $table->string('transaction_type', 100)->index();
            $table->integer('total_detail')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('als_import_log');
    }
};
