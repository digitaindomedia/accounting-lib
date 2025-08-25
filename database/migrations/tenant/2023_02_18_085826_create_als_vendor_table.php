<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsVendorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_vendor', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_name',254);
            $table->string('vendor_code',254);
            $table->string('vendor_ktp',254);
            $table->string('vendor_company_name',254);
            $table->string('vendor_npwp',254);
            $table->text('vendor_address')->nullable();
            $table->double('vendor_max')->default(0);
            $table->double('vendor_duration')->default(0);
            $table->string('vendor_duration_by')->default('NULL');
            $table->string('vendor_photo',254)->default('NULL');
            $table->string('vendor_pkp_status',40)->default('NULL');
            $table->string('vendor_pkp_no',40)->default('NULL');
            $table->string('vendor_pkp_date',20)->default('NULL');
            $table->string('vendor_email',254)->default('NULL');
            $table->string('vendor_phone',100)->default('NULL');
            $table->string('vendor_status',100)->default('AKTIF');
            $table->unsignedBigInteger('coa_id')->default(0);
            $table->integer('country_id')->default(0);
            $table->integer('aging')->default(0);
            $table->string('aging_by',100)->default('NULL');
            $table->string('kawasan_berikat',100)->default('NULL');
            $table->string('vendor_type',100)->default('customer');
            $table->datetime('created_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('created_by');
            $table->datetime('updated_at')->default(\Illuminate\Support\Facades\DB::raw('CURRENT_TIMESTAMP'));;
            $table->integer('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('als_vendor');
    }
}
