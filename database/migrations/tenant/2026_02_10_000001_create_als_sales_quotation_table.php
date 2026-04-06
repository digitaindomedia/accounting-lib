<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAlsSalesQuotationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('als_sales_quotation', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_no',254);
            $table->date('quotation_date');
            $table->text('note')->nullable();
            $table->string('quotation_status',30);
            $table->text('reason')->nullable();
            $table->string('tax_type',40)->nullable();
            $table->double('subtotal')->default(0);
            $table->double('dpp')->default(0);
            $table->double('total_tax')->default(0);
            $table->double('grandtotal')->default(0);
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
        Schema::dropIfExists('als_sales_quotation');
    }
}
