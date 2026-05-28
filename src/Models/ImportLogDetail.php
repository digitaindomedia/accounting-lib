<?php

namespace Icso\Accounting\Models;

use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrder;
use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequest;
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Models\Persediaan\StockUsage;
use Icso\Accounting\Models\Akuntansi\Jurnal;
use Illuminate\Database\Eloquent\Model;

class ImportLogDetail extends Model
{
    protected $table = 'als_import_log_detail';
    protected $guarded = [];
    public $timestamps = false;

    public function importLog()
    {
        return $this->belongsTo(ImportLog::class, 'import_log_id');
    }

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoicing::class, 'transaksi_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'transaksi_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'transaksi_id');
    }

    public function purchaseInvoice()
    {
        return $this->belongsTo(PurchaseInvoicing::class, 'transaksi_id');
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'transaksi_id');
    }

    public function jurnal()
    {
        return $this->belongsTo(Jurnal::class, 'transaksi_id');
    }

    public function adjustment()
    {
        return $this->belongsTo(Adjustment::class, 'transaksi_id');
    }

    public function stockUsage()
    {
        return $this->belongsTo(StockUsage::class, 'transaksi_id');
    }
}
