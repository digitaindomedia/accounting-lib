<?php

namespace Icso\Accounting\Repositories\Persediaan\Inventory\Interface;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedProduct;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDeliveryProduct;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo as PurchaseInvoiceRepo;
use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo as PurchaseReceiveRepo;
use Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo as SalesDeliveryRepo;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo as SalesInvoiceRepo;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryRepo extends ElequentRepository
{

    protected $model;

    public function __construct(Inventory $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }


    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->whereHas('product', function ($query) use ($search) {
                $query->where('item_name', 'like', '%' .$search. '%');
                $query->orWhere('item_code', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->with(['product','coa','warehouse','unit'])->orderBy('inventory_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->whereHas('product', function ($query) use ($search) {
                $query->where('item_name', 'like', '%' .$search. '%');
                $query->orWhere('item_code', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
                $query->where($where);
            })->with(['product','coa','warehouse','unit'])->orderBy('inventory_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $inventoryDate = $request->inventory_date;
        $transactionCode = $request->transaction_code;
        $userId = $request->user_id;
        $transactionId = !empty($request->transaction_id) ? $request->transaction_id : 0;
        $transactionSubId = !empty($request->transaction_sub_id) ? $request->transaction_sub_id : 0;
        $qtyIn = !empty($request->qty_in) ? (float) $request->qty_in : 0;
        $qtyOut = !empty($request->qty_out) ? (float) $request->qty_out : 0;
        $coaId = !empty($request->coa_id) ? $request->coa_id : 0;
        $warehouseId = !empty($request->warehouse_id) ? $request->warehouse_id : '0';
        $productId = !empty($request->product_id) ? $request->product_id : '0';
        $note = !empty($request->note) ? $request->note : '';
        $price = (float) Utility::remove_commas($request->price);
        $unitId = !empty($request->unit_id) ? $request->unit_id : 0;


        if(!empty($qtyIn)){
            $findProduct = Product::where(array('id' => $productId))->first();
            if(!empty($findProduct)){
                if($findProduct->unit_id != $unitId){
                    $findConvertion = ProductConvertion::where(array('product_id' => $productId, 'unit_id' => $unitId))->first();
                    if(!empty($findConvertion)){
                        $nilai = (float) $findConvertion->nilai_terkecil;
                        $qtyIn = $qtyIn * $nilai;
                        $price = $price / $nilai;
                    }
                }
            }
        }

        if(!empty($qtyOut)){
            $findProduct = Product::where(array('id' => $productId))->first();
            if(!empty($findProduct)){
                if($findProduct->unit_id != $unitId){
                    $findConvertion = ProductConvertion::where(array('product_id' => $productId, 'unit_id' => $unitId))->first();
                    if(!empty($findConvertion)){
                        $nilai = (float) $findConvertion->nilai_terkecil;
                        $qtyOut = $qtyOut * $nilai;
                        $price = $price / $nilai;
                    }
                }
            }
        }

        $totalIn = $qtyIn * $price;
        $totalOut = $qtyOut * $price;

        if(!empty($request->adjustment_type)){
            if($request->adjustment_type == VarType::ADJUSTMENT_TYPE_VALUE){
                if($qtyIn == 0 && $qtyOut == 0){
                    if($request->jenis == 'masuk'){
                        $totalOut =0;
                        $totalIn = abs($price);
                        $price = 0;
                    } else {
                        $totalOut = abs($price);
                        $totalIn = 0;
                        $price = 0;
                    }
                } else{
                    if($request->jenis == 'masuk') {
                        $totalIn = abs($price);
                        if ($qtyIn != 0) {
                            $price = $totalIn / $qtyIn;
                        }
                        if ($qtyOut != 0) {
                            $price = $totalIn / $qtyOut;
                        }

                    } else {
                        if($request->jenis == 'val_masuk'){
                            $totalOut = abs($price);
                            $price = 0;
                        }
                        else if($request->jenis == 'val_keluar')
                        {
                            $totalIn = abs($price);
                            $price = 0;
                        }
                        else {
                            $totalOut = $price;
                            $totalOut = abs($totalOut);
                            if($qtyIn != 0) {
                                $price = $totalOut / $qtyIn;
                            }
                            if($qtyOut != 0) {
                                $price = $totalOut / $qtyOut;
                            }
                        }

                    }
                }
            }
        }

        $arrData = array(
            'inventory_date' => $inventoryDate,
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'nominal' => $price,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'transaction_code' => $transactionCode,
            'transaction_id' => $transactionId,
            'transaction_sub_id' => $transactionSubId,
            'unit_id' => $unitId,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'note' => $note,
            'coa_id' => $coaId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
            'created_by' => $userId
        );
        $res = $this->create($arrData);
        return $res;
    }

    public function getStokByDate($productId, $warehouseId, $unitId, $date)
    {
        // TODO: Implement getStokByDate() method.
        $tableName = Inventory::getTableName();
        $sql = "SELECT SUM(qty_in) as qtyIn,SUM(qty_out) as qtyOut FROM $tableName WHERE unit_id='$unitId' AND warehouse_id = '$warehouseId' AND product_id = '$productId' AND inventory_date <= '$date'";
        $res = DB::select($sql);
        $total = 0;
        if(count($res) > 0)
        {
            $qtyIn = $res[0]->qtyIn;
            $qtyOut = $res[0]->qtyOut;
            $total = $qtyIn - $qtyOut;
        }
        return $total;
    }

    /**
     * Rebuild full inventory ledger from source transactions.
     * Sequence:
     * 1. Saldo awal
     * 2. Penerimaan pembelian
     * 3. Invoice pembelian tanpa penerimaan
     * 4. Pengiriman penjualan
     * 5. Invoice penjualan tanpa pengiriman
     */
    public function recalculateStock(?string $actorId = null): array
    {
        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table(Inventory::getTableName())->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $summary = [
                'saldo_awal' => $this->rebuildFromStockAwal($actorId),
                'penerimaan' => $this->rebuildFromPurchaseReceive($actorId),
                'invoice_pembelian_tanpa_penerimaan' => $this->rebuildFromDirectPurchaseInvoice($actorId),
                'pengiriman' => $this->rebuildFromSalesDelivery($actorId),
                'invoice_penjualan_tanpa_pengiriman' => $this->rebuildFromDirectSalesInvoice($actorId),
            ];
            $journalSummary = $this->repostAccountingJournals();

            DB::commit();
            return [
                'status' => true,
                'summary' => $summary,
                'journal_summary' => $journalSummary,
                'total' => array_sum($summary),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            throw $e;
        }
    }

    private function rebuildFromStockAwal(?string $actorId = null): int
    {
        $counter = 0;
        StockAwal::orderBy('stock_date', 'asc')
            ->orderBy('id', 'asc')
            ->chunk(500, function ($rows) use (&$counter, $actorId) {
                foreach ($rows as $row) {
                    $req = new Request();
                    $req->coa_id = $row->coa_id;
                    $req->user_id = $this->resolveActorId($actorId, $row->created_by);
                    $req->inventory_date = $row->stock_date;
                    $req->transaction_code = TransactionsCode::SALDO_AWAL;
                    $req->qty_in = $row->qty;
                    $req->warehouse_id = $row->warehouse_id;
                    $req->product_id = $row->product_id;
                    $req->price = $row->nominal;
                    $req->unit_id = $row->unit_id;
                    $req->transaction_id = $row->id;
                    $this->store($req);
                    $counter++;
                }
            });
        return $counter;
    }

    private function rebuildFromPurchaseReceive(?string $actorId = null): int
    {
        $counter = 0;
        $receiveTable = (new PurchaseReceived())->getTable();
        $receiveProductTable = (new PurchaseReceivedProduct())->getTable();
        $productTable = Product::getTableName();

        DB::table($receiveProductTable . ' as rp')
            ->join($receiveTable . ' as r', 'r.id', '=', 'rp.receive_id')
            ->leftJoin($productTable . ' as p', 'p.id', '=', 'rp.product_id')
            ->select([
                'rp.id as receive_product_id',
                'rp.receive_id',
                'rp.qty',
                'rp.unit_id',
                'rp.product_id',
                'rp.hpp_price',
                'p.coa_id as product_coa_id',
                'r.receive_date',
                'r.warehouse_id',
                'r.note',
                'r.created_by',
            ])
            ->orderBy('r.receive_date', 'asc')
            ->orderBy('r.id', 'asc')
            ->orderBy('rp.id', 'asc')
            ->chunk(500, function ($rows) use (&$counter, $actorId) {
                foreach ($rows as $row) {
                    $req = new Request();
                    $req->coa_id = $row->product_coa_id ?: 0;
                    $req->user_id = $this->resolveActorId($actorId, $row->created_by);
                    $req->inventory_date = $row->receive_date;
                    $req->transaction_code = TransactionsCode::PENERIMAAN;
                    $req->qty_in = (float) $row->qty;
                    $req->warehouse_id = $row->warehouse_id;
                    $req->product_id = $row->product_id;
                    $req->price = (float) $row->hpp_price;
                    $req->note = $row->note;
                    $req->unit_id = $row->unit_id;
                    $req->transaction_id = $row->receive_id;
                    $req->transaction_sub_id = $row->receive_product_id;
                    $this->store($req);
                    $counter++;
                }
            });

        return $counter;
    }

    private function rebuildFromDirectPurchaseInvoice(?string $actorId = null): int
    {
        $counter = 0;
        $invoiceTable = (new PurchaseInvoicing())->getTable();
        $orderProductTable = 'als_purchase_order_product';
        $invoiceReceiveTable = 'als_purchase_invoice_receive';
        $productTable = Product::getTableName();

        DB::table($orderProductTable . ' as op')
            ->join($invoiceTable . ' as i', 'i.id', '=', 'op.invoice_id')
            ->leftJoin($invoiceReceiveTable . ' as ir', 'ir.invoice_id', '=', 'i.id')
            ->leftJoin($productTable . ' as p', 'p.id', '=', 'op.product_id')
            ->whereNull('ir.id')
            ->where('op.product_id', '!=', 0)
            ->where('op.qty', '>', 0)
            ->orderBy('i.invoice_date', 'asc')
            ->orderBy('i.id', 'asc')
            ->orderBy('op.id', 'asc')
            ->select([
                'i.id as invoice_id',
                'i.invoice_date',
                'i.warehouse_id',
                'i.note',
                'i.created_by',
                'op.id as order_product_id',
                'op.product_id',
                'op.unit_id',
                'op.qty',
                'op.price',
                'op.discount',
                'op.discount_type',
                'op.tax_id',
                'op.tax_type',
                'op.tax_percentage',
                'op.subtotal',
                'p.coa_id as product_coa_id',
            ])
            ->chunk(500, function ($rows) use (&$counter, $actorId) {
                foreach ($rows as $row) {
                    $hpp = (float) $row->price;
                    $qty = (float) $row->qty;
                    if ($qty != 0.0) {
                        $subtotalHpp = $qty * (float) $row->price;

                        if ((float) $row->discount > 0) {
                            if ($row->discount_type == TypeEnum::DISCOUNT_TYPE_PERCENT) {
                                $subtotalHpp -= ((float) $row->discount / 100) * $subtotalHpp;
                            } else {
                                $subtotalHpp -= (float) $row->discount;
                            }
                        }

                        if (!empty($row->tax_id) && $row->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                            $pembagi = ((float) $row->tax_percentage + 100) / 100;
                            if ($pembagi != 0.0) {
                                $subtotalHpp = $subtotalHpp / $pembagi;
                            }
                        }

                        $hpp = $subtotalHpp / $qty;
                    }

                    $req = new Request();
                    $req->coa_id = $row->product_coa_id ?: 0;
                    $req->user_id = $this->resolveActorId($actorId, $row->created_by);
                    $req->inventory_date = $row->invoice_date;
                    $req->transaction_code = TransactionsCode::INVOICE_PEMBELIAN;
                    $req->qty_in = (float) $row->qty;
                    $req->warehouse_id = $row->warehouse_id;
                    $req->product_id = $row->product_id;
                    $req->price = $hpp;
                    $req->note = $row->note;
                    $req->unit_id = $row->unit_id;
                    $req->transaction_id = $row->invoice_id;
                    $req->transaction_sub_id = $row->order_product_id;
                    $this->store($req);
                    $counter++;
                }
            });

        return $counter;
    }

    private function rebuildFromSalesDelivery(?string $actorId = null): int
    {
        $counter = 0;
        $deliveryTable = (new SalesDelivery())->getTable();
        $deliveryProductTable = (new SalesDeliveryProduct())->getTable();
        $productTable = Product::getTableName();

        DB::table($deliveryProductTable . ' as dp')
            ->join($deliveryTable . ' as d', 'd.id', '=', 'dp.delivery_id')
            ->leftJoin($productTable . ' as p', 'p.id', '=', 'dp.product_id')
            ->where('dp.product_id', '!=', 0)
            ->where('dp.qty', '>', 0)
            ->orderBy('d.delivery_date', 'asc')
            ->orderBy('d.id', 'asc')
            ->orderBy('dp.id', 'asc')
            ->select([
                'd.id as delivery_id',
                'd.delivery_date',
                'd.warehouse_id',
                'd.note',
                'd.created_by',
                'dp.id as delivery_product_id',
                'dp.product_id',
                'dp.unit_id',
                'dp.qty',
                'p.coa_id as product_coa_id',
            ])
            ->chunk(500, function ($rows) use (&$counter, $actorId) {
                foreach ($rows as $row) {
                    $hpp = $this->movingAverageByDate($row->product_id, $row->unit_id, $row->delivery_date);

                    $req = new Request();
                    $req->coa_id = $row->product_coa_id ?: 0;
                    $req->user_id = $this->resolveActorId($actorId, $row->created_by);
                    $req->inventory_date = $row->delivery_date;
                    $req->transaction_code = TransactionsCode::DELIVERY_ORDER;
                    $req->qty_out = (float) $row->qty;
                    $req->warehouse_id = $row->warehouse_id;
                    $req->product_id = $row->product_id;
                    $req->price = (float) $hpp;
                    $req->note = $row->note;
                    $req->unit_id = $row->unit_id;
                    $req->transaction_id = $row->delivery_id;
                    $req->transaction_sub_id = $row->delivery_product_id;
                    $this->store($req);
                    $counter++;
                }
            });

        return $counter;
    }

    private function rebuildFromDirectSalesInvoice(?string $actorId = null): int
    {
        $counter = 0;
        $invoiceTable = (new SalesInvoicing())->getTable();
        $orderProductTable = (new SalesOrderProduct())->getTable();
        $invoiceDeliveryTable = 'als_sales_invoice_delivery';
        $productTable = Product::getTableName();

        DB::table($orderProductTable . ' as op')
            ->join($invoiceTable . ' as i', 'i.id', '=', 'op.invoice_id')
            ->leftJoin($invoiceDeliveryTable . ' as idv', 'idv.invoice_id', '=', 'i.id')
            ->leftJoin($productTable . ' as p', 'p.id', '=', 'op.product_id')
            ->whereNull('idv.id')
            ->where('i.invoice_type', '!=', ProductType::SERVICE)
            ->where('op.product_id', '!=', 0)
            ->where('op.qty', '>', 0)
            ->orderBy('i.invoice_date', 'asc')
            ->orderBy('i.id', 'asc')
            ->orderBy('op.id', 'asc')
            ->select([
                'i.id as invoice_id',
                'i.invoice_date',
                'i.warehouse_id',
                'i.note',
                'i.created_by',
                'op.id as order_product_id',
                'op.product_id',
                'op.unit_id',
                'op.qty',
                'p.coa_id as product_coa_id',
            ])
            ->chunk(500, function ($rows) use (&$counter, $actorId) {
                foreach ($rows as $row) {
                    $hpp = $this->movingAverageByDate($row->product_id, $row->unit_id, $row->invoice_date);

                    $req = new Request();
                    $req->coa_id = $row->product_coa_id ?: 0;
                    $req->user_id = $this->resolveActorId($actorId, $row->created_by);
                    $req->inventory_date = $row->invoice_date;
                    $req->transaction_code = TransactionsCode::INVOICE_PENJUALAN;
                    $req->qty_out = (float) $row->qty;
                    $req->warehouse_id = $row->warehouse_id;
                    $req->product_id = $row->product_id;
                    $req->price = (float) $hpp;
                    $req->note = $row->note;
                    $req->unit_id = $row->unit_id;
                    $req->transaction_id = $row->invoice_id;
                    $req->transaction_sub_id = $row->order_product_id;
                    $this->store($req);
                    $counter++;
                }
            });

        return $counter;
    }

    private function resolveActorId(?string $actorId, $fallbackUserId): string
    {
        if (!empty($actorId)) {
            return (string) $actorId;
        }
        if (!empty($fallbackUserId)) {
            return (string) $fallbackUserId;
        }
        return '0';
    }

    private function repostAccountingJournals(): array
    {
        $summary = [
            'penerimaan' => 0,
            'invoice_pembelian' => 0,
            'invoice_pembelian_skipped_non_purchase' => 0,
            'invoice_pembelian_skipped_invalid_detail' => 0,
            'pengiriman' => 0,
            'invoice_penjualan' => 0,
            'invoice_penjualan_pos_skipped' => 0,
        ];

        $purchaseReceiveRepo = new PurchaseReceiveRepo(new PurchaseReceived());
        PurchaseReceived::orderBy('receive_date', 'asc')->orderBy('id', 'asc')->chunk(200, function ($rows) use (&$summary, $purchaseReceiveRepo) {
            foreach ($rows as $row) {
                JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PENERIMAAN, $row->id);
                $purchaseReceiveRepo->postingJurnal($row->id);
                $summary['penerimaan']++;
            }
        });

        $purchaseInvoiceRepo = new PurchaseInvoiceRepo(new PurchaseInvoicing());
        PurchaseInvoicing::orderBy('invoice_date', 'asc')->orderBy('id', 'asc')->chunk(200, function ($rows) use (&$summary, $purchaseInvoiceRepo) {
            foreach ($rows as $row) {
                if ($row->input_type !== InputType::PURCHASE) {
                    $summary['invoice_pembelian_skipped_non_purchase']++;
                    continue;
                }

                $hasReceived = DB::table('als_purchase_invoice_receive')->where('invoice_id', $row->id)->exists();
                $hasOrderProduct = DB::table('als_purchase_order_product')->where('invoice_id', $row->id)->exists();
                $hasOrderFallback = !empty($row->order_id)
                    ? DB::table('als_purchase_order_product')->where('order_id', $row->order_id)->exists()
                    : false;

                if (!$hasReceived && !$hasOrderProduct && !$hasOrderFallback) {
                    $summary['invoice_pembelian_skipped_invalid_detail']++;
                    continue;
                }

                JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::INVOICE_PEMBELIAN, $row->id);
                $purchaseInvoiceRepo->postingJurnal($row->id);
                $summary['invoice_pembelian']++;
            }
        });

        $salesDeliveryRepo = new SalesDeliveryRepo(new SalesDelivery());
        SalesDelivery::orderBy('delivery_date', 'asc')->orderBy('id', 'asc')->chunk(200, function ($rows) use (&$summary, $salesDeliveryRepo) {
            foreach ($rows as $row) {
                JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::DELIVERY_ORDER, $row->id);
                $salesDeliveryRepo->postingJurnal($row->id, true, true);
                $summary['pengiriman']++;
            }
        });

        $salesInvoiceRepo = new SalesInvoiceRepo(new SalesInvoicing());
        SalesInvoicing::orderBy('invoice_date', 'asc')->orderBy('id', 'asc')->chunk(200, function ($rows) use (&$summary, $salesInvoiceRepo) {
            foreach ($rows as $row) {
                if ($row->input_type == InputType::POS) {
                    $summary['invoice_penjualan_pos_skipped']++;
                    continue;
                }
                JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::INVOICE_PENJUALAN, $row->id);
                $salesInvoiceRepo->postingJurnal($row->id, true, true);
                $summary['invoice_penjualan']++;
            }
        });

        return $summary;
    }

    public function movingAverageByDate($productId, $unitId, $date)
    {
        // TODO: Implement movingAverageByDate() method.
        $totalIn = Inventory::where([['unit_id','=',$unitId],['product_id','=',$productId],['inventory_date','<=',$date]])->orderBy('inventory_date','ASC')->sum('total_in');
        $totalOut = Inventory::where([['unit_id','=',$unitId],['product_id','=',$productId],['inventory_date','<=',$date]])->orderBy('inventory_date','ASC')->sum('total_out');
        $qtyIn = Inventory::where([['unit_id','=',$unitId],['product_id','=',$productId],['inventory_date','<=',$date]])->orderBy('inventory_date','ASC')->sum('qty_in');
        $qtyOut = Inventory::where([['unit_id','=',$unitId],['product_id','=',$productId],['inventory_date','<=',$date]])->orderBy('inventory_date','ASC')->sum('qty_out');
        $hpp = 0;
        $total = $totalIn - $totalOut;
        $qty = $qtyIn - $qtyOut;
        if($qty != 0)
        {
            $hpp = $total / $qty;
            if($hpp < 0)
            {
                $hpp = 0;
            }
        }
        /* $tableName = Inventory::getTableName();
        $sql = "SELECT SUM(total_in) as totalIn,SUM(total_out) as totalOut,SUM(qty_in) as qtyIn,SUM(qty_out) as qtyOut FROM $tableName WHERE unit_id='$unitId' AND product_id = '$productId' AND DATE(inventory_date) <= '$date' ORDER BY inventory_date ASC ";
        $q_res = DB::select(DB::raw($sql));
        $hpp = 0;
        if(count($q_res) > 0)
        {
         //   echo $q_res[0]->totalIn;
            $totalIn = $q_res[0]->totalIn;
            $totalOut = $q_res[0]->totalOut;
            $qtyIn = $q_res[0]->qtyIn;
            $qtyOut = $q_res[0]->qtyOut;
            $total = $totalIn - $totalOut;
            $qty = $qtyIn - $qtyOut;
            if($qty != 0)
            {
                $hpp = $total / $qty;
                if($hpp < 0)
                {
                    $hpp = 0;
                }
            }

        }*/
        return $hpp;
    }

    public function getTotalSaldoAwalByCoaType($coaId)
    {
        // TODO: Implement getTotalSaldoAwalByCoaType() method.
        $tableName = Inventory::getTableName();
        $sql = "SELECT COALESCE(SUM(total_in),0) as total FROM $tableName WHERE coa_id = '$coaId' AND transaction_code = '".TransactionsCode::SALDO_AWAL."' ";
        $q_res = DB::select($sql);
        $saldo = 0;
        if(count($q_res) > 0) {
            $saldo = $q_res[0]->total;

        }
        return $saldo;
    }

    public function getAllDataStockAwalBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new StockAwal();
        $dataSet = $model->select(StockAwal::getTableName().".*")
            ->join(Product::getTableName(),StockAwal::getTableName().".product_id", "=", Product::getTableName().".id")
            ->when(!empty($search), function ($query) use($search){
                $query->where(Product::getTableName().".item_name", 'like', '%' .$search. '%')->orWhere(Product::getTableName().".item_code", 'like', '%' .$search. '%');
            })->when(!empty($where), function ($query) use($where){
                $query->where($where);
            })->with(['product','coa','warehouse','unit'])->orderBy(StockAwal::getTableName().'.stock_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataStockAwalBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new StockAwal();
        $dataSet = $model->join(Product::getTableName(),StockAwal::getTableName().".product_id", "=", Product::getTableName().".id")
            ->when(!empty($search), function ($query) use($search){
                $query->where(Product::getTableName().".item_name", 'like', '%' .$search. '%')->orWhere(Product::getTableName().".item_code", 'like', '%' .$search. '%');
            })->when(!empty($where), function ($query) use($where){
                $query->where($where);
            })->orderBy(StockAwal::getTableName().'.stock_date','desc')->count();
        return $dataSet;
    }

    public static function deleteInventory($transactionCode, $idTransaction)
    {
        $res = Inventory::where(array('transaction_code' => $transactionCode, 'transaction_id' => $idTransaction))->delete();
        return $res;
    }

    public function findByTransCodeIdSubId($transactionCode, $idTransaction, $transaction_sub_id)
    {
        $res = Inventory::where(array('transaction_code' => $transactionCode, 'transaction_sub_id' => $idTransaction))->first();
        return $res;
    }

    public static function getTotalStockBySaldoAwalCoaId($coaId)
    {
        $getTotal = StockAwal::where(array('coa_id' => $coaId))->sum('total');
        return $getTotal;
    }

    public static function getStokBy($productId, $warehouseId, $dari, $sampai='', $sign='between')
    {
        // TODO: Implement getStokByDate() method.
        $qtyIn = 0;
        $qtyOut = 0;
        if($sign == 'between') {
            if(!empty($warehouseId)){
                $qtyIn = Inventory::where([['product_id','=',$productId], ['warehouse_id','=',$warehouseId]])->whereBetween('inventory_date',[$dari,$sampai])->sum('qty_in');
                $qtyOut = Inventory::where([['product_id','=',$productId], ['warehouse_id','=',$warehouseId]])->whereBetween('inventory_date',[$dari,$sampai])->sum('qty_out');
            } else {
                $qtyIn = Inventory::where([['product_id','=',$productId]])->whereBetween('inventory_date',[$dari,$sampai])->sum('qty_in');
                $qtyOut = Inventory::where([['product_id','=',$productId]])->whereBetween('inventory_date',[$dari,$sampai])->sum('qty_out');
            }
        } else {
            if(!empty($warehouseId)){
                $qtyIn = Inventory::where([['product_id','=',$productId], ['warehouse_id','=',$warehouseId],['inventory_date', $sign, $dari]])->sum('qty_in');
                $qtyOut = Inventory::where([['product_id','=',$productId], ['warehouse_id','=',$warehouseId],['inventory_date', $sign, $dari]])->sum('qty_out');
            } else {
                $qtyIn = Inventory::where([['product_id','=',$productId],['inventory_date', $sign, $dari]])->sum('qty_in');
                $qtyOut = Inventory::where([['product_id','=',$productId],['inventory_date', $sign, $dari]])->sum('qty_out');
            }
        }
        $total = $qtyIn - $qtyOut;
        return array(
            'qty_in' => $qtyIn,
            'qty_out' => $qtyOut,
            'total' => $total
        );
    }

    public static function getStokValueBy($productId, $warehouseId, $dari, $sampai='', $sign='between')
    {
        // TODO: Implement getStokByDate() method.
        $totalIn = 0;
        $totalOut = 0;
        if($sign == 'between') {
            if(!empty($warehouseId)){
                $totalIn = Inventory::where([['product_id','=',$productId], ['warehouse_id','=',$warehouseId]])->whereBetween('inventory_date',[$dari,$sampai])->sum('total_in');
                $totalOut = Inventory::where([['product_id','=',$productId], ['warehouse_id','=',$warehouseId]])->whereBetween('inventory_date',[$dari,$sampai])->sum('total_out');
            } else {
                $totalIn = Inventory::where([['product_id','=',$productId]])->whereBetween('inventory_date',[$dari,$sampai])->sum('total_in');
                $totalOut = Inventory::where([['product_id','=',$productId]])->whereBetween('inventory_date',[$dari,$sampai])->sum('total_out');
            }
        } else {
            if(!empty($warehouseId)){
                $totalIn = Inventory::where([['product_id','=',$productId], ['warehouse_id','=',$warehouseId],['inventory_date', $sign, $dari]])->sum('total_in');
                $totalOut = Inventory::where([['product_id','=',$productId], ['warehouse_id','=',$warehouseId],['inventory_date', $sign, $dari]])->sum('total_out');
            } else {
                $totalIn = Inventory::where([['product_id','=',$productId],['inventory_date', $sign, $dari]])->sum('total_in');
                $totalOut = Inventory::where([['product_id','=',$productId],['inventory_date', $sign, $dari]])->sum('total_out');
            }
        }
        $total = $totalIn - $totalOut;
        return array(
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'total' => $total
        );
    }
}
