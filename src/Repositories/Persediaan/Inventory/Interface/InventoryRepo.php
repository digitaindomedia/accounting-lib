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
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Models\Persediaan\StockUsage;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo as PurchaseInvoiceRepo;
use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo as PurchaseReceiveRepo;
use Icso\Accounting\Repositories\Persediaan\Adjustment\AdjustmentRepo;
use Icso\Accounting\Repositories\Persediaan\Pemakaian\PemakaianRepo;
use Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo as SalesDeliveryRepo;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo as SalesInvoiceRepo;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryRepo extends ElequentRepository
{

    use RebuildsInventory;

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


        if ($qtyIn != 0 || $qtyOut != 0) {
            $factor = $this->getConversionFactorToSmallest($productId, $unitId);
            $qtyIn *= $factor;
            $qtyOut *= $factor;
            // A value adjustment supplies a total amount, not a unit price.
            if ($request->adjustment_type != VarType::ADJUSTMENT_TYPE_VALUE) {
                $price /= $factor;
            }
        }

        if (self::$rebuildCreatedAt !== null && $qtyOut > 0) {
            $available = (float) Inventory::where('product_id', $productId)
                ->selectRaw('COALESCE(SUM(qty_in - qty_out), 0) AS qty')->value('qty');
            if ($qtyOut > $available + 0.00000001) {
                $document = "{$transactionCode} (ID transaksi {$transactionId}, ID detail {$transactionSubId})";
                if ($transactionCode === TransactionsCode::INVOICE_PENJUALAN) {
                    $invoiceNo = SalesInvoicing::whereKey($transactionId)->value('invoice_no');
                    $invoiceNo = trim((string) $invoiceNo) !== '' ? $invoiceNo : '(belum diisi)';
                    $document = "{$transactionCode}, no invoice {$invoiceNo} (ID invoice {$transactionId}, ID detail {$transactionSubId})";
                }
                $shortage = $qtyOut - $available;
                $lastHpp = $this->lastPositiveBalanceHpp($productId, $inventoryDate);
                if ($lastHpp === null) {
                    throw new \RuntimeException("Stok global negatif saat rebuild {$document}, ID barang {$productId}: tersedia {$available}, keluar {$qtyOut}. HPP terakhir belum tersedia; rebuild dibatalkan.");
                }
                $warning = "Stok global negatif saat rebuild {$document}, ID barang {$productId}: tersedia {$available}, keluar {$qtyOut}, kekurangan {$shortage} (satuan terkecil). HPP terakhir sebelum minus {$lastHpp}; HPP transaksi {$price} per satuan terkecil.";
                self::$rebuildWarnings[] = $warning;
                $note = trim($note . ' ' . $warning);
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
            'created_at' => self::$rebuildCreatedAt ?? date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
            'created_by' => $userId
        );
        $res = $this->create($arrData);
        return $res;
    }

    public function getStokByDate($productId, $warehouseId, $unitId, $date)
    {
        $query = Inventory::where('product_id', $productId)
            ->where('inventory_date', '<=', $this->inventoryDateCutoff($date));

        if (!empty($warehouseId)) {
            $query->where('warehouse_id', $warehouseId);
        }

        $stock = $query->select(
            DB::raw('COALESCE(SUM(qty_in), 0) as qty_in'),
            DB::raw('COALESCE(SUM(qty_out), 0) as qty_out')
        )->first();

        return (((float) $stock->qty_in) - ((float) $stock->qty_out))
            / $this->getConversionFactorToSmallest($productId, $unitId);
    }

    private function rebuildFromStockAwal(?string $actorId = null, $sourceId = null): int
    {
        $counter = 0;
        StockAwal::whereKey($sourceId)->orderBy('stock_date', 'asc')
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

    private function rebuildFromPurchaseReceive(?string $actorId = null, $sourceId = null): int
    {
        $counter = 0;
        $receiveTable = (new PurchaseReceived())->getTable();
        $receiveProductTable = (new PurchaseReceivedProduct())->getTable();
        $productTable = Product::getTableName();

        DB::table($receiveProductTable . ' as rp')
            ->join($receiveTable . ' as r', 'r.id', '=', 'rp.receive_id')
            ->where('r.id', $sourceId)
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

    private function rebuildFromDirectPurchaseInvoice(?string $actorId = null, $sourceId = null): int
    {
        $counter = 0;
        $invoiceTable = (new PurchaseInvoicing())->getTable();
        $orderProductTable = 'als_purchase_order_product';
        $invoiceReceiveTable = 'als_purchase_invoice_receive';
        $productTable = Product::getTableName();

        DB::table($orderProductTable . ' as op')
            ->join($invoiceTable . ' as i', 'i.id', '=', 'op.invoice_id')
            ->where('i.id', $sourceId)
            ->leftJoin($invoiceReceiveTable . ' as ir', 'ir.invoice_id', '=', 'i.id')
            ->leftJoin($productTable . ' as p', 'p.id', '=', 'op.product_id')
            ->whereNull('ir.id')
            ->where('i.input_type', InputType::PURCHASE)
            ->where('i.invoice_type', ProductType::ITEM)
            ->where('p.product_type', ProductType::ITEM)
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

    private function rebuildFromAdjustment($sourceId = null): int
    {
        $counter = 0;
        $adjustmentRepo = new AdjustmentRepo(new Adjustment(), app(ActivityLogService::class));

        Adjustment::whereKey($sourceId)->with(['adjustmentproduct' => fn ($q) => $q->orderBy('id')])
            ->orderBy('adjustment_date', 'asc')
            ->orderBy('id', 'asc')
            ->chunk(200, function ($rows) use (&$counter, $adjustmentRepo) {
                foreach ($rows as $row) {
                    JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::ADJUSTMENT, $row->id);
                    Inventory::where([
                        'transaction_code' => TransactionsCode::ADJUSTMENT,
                        'transaction_id' => $row->id,
                    ])->delete();

                    $adjustmentRepo->postingJurnal($row->id);
                    $counter += $row->adjustmentproduct->count();
                }
            });

        return $counter;
    }

    private function rebuildFromStockUsage($sourceId = null): int
    {
        $counter = 0;
        $pemakaianRepo = new PemakaianRepo(new StockUsage(), app(ActivityLogService::class));

        StockUsage::whereKey($sourceId)->with(['stockusageproduct' => fn ($q) => $q->orderBy('id')])
            ->orderBy('usage_date', 'asc')
            ->orderBy('id', 'asc')
            ->chunk(200, function ($rows) use (&$counter, $pemakaianRepo) {
                foreach ($rows as $row) {
                    JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PEMAKAIAN_STOCK, $row->id);
                    Inventory::where([
                        'transaction_code' => TransactionsCode::PEMAKAIAN_STOCK,
                        'transaction_id' => $row->id,
                    ])->delete();

                    $pemakaianRepo->postingJurnal($row->id);
                    $counter += $row->stockusageproduct->count();
                }
            });

        return $counter;
    }

    private function rebuildFromSalesDelivery(?string $actorId = null, $sourceId = null): int
    {
        $counter = 0;
        $deliveryTable = (new SalesDelivery())->getTable();
        $deliveryProductTable = (new SalesDeliveryProduct())->getTable();
        $productTable = Product::getTableName();

        DB::table($deliveryProductTable . ' as dp')
            ->join($deliveryTable . ' as d', 'd.id', '=', 'dp.delivery_id')
            ->where('d.id', $sourceId)
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
                    $cost = $this->resolveOutgoingCost($row->product_id, $row->unit_id, $row->qty, $row->delivery_date);

                    $req = new Request();
                    $req->coa_id = $row->product_coa_id ?: 0;
                    $req->user_id = $this->resolveActorId($actorId, $row->created_by);
                    $req->inventory_date = $row->delivery_date;
                    $req->transaction_code = TransactionsCode::DELIVERY_ORDER;
                    $req->qty_out = (float) $row->qty;
                    $req->warehouse_id = $row->warehouse_id;
                    $req->product_id = $row->product_id;
                    $req->price = (float) $cost['hpp_unit'];
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

    private function rebuildFromDirectSalesInvoice(?string $actorId = null, $sourceId = null): int
    {
        $counter = 0;
        $invoiceTable = (new SalesInvoicing())->getTable();
        $orderProductTable = (new SalesOrderProduct())->getTable();
        $invoiceDeliveryTable = 'als_sales_invoice_delivery';
        $productTable = Product::getTableName();

        DB::table($orderProductTable . ' as op')
            ->join($invoiceTable . ' as i', 'i.id', '=', 'op.invoice_id')
            ->where('i.id', $sourceId)
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
                    $cost = $this->resolveOutgoingCost($row->product_id, $row->unit_id, $row->qty, $row->invoice_date);

                    $req = new Request();
                    $req->coa_id = $row->product_coa_id ?: 0;
                    $req->user_id = $this->resolveActorId($actorId, $row->created_by);
                    $req->inventory_date = $row->invoice_date;
                    $req->transaction_code = TransactionsCode::INVOICE_PENJUALAN;
                    $req->qty_out = (float) $row->qty;
                    $req->warehouse_id = $row->warehouse_id;
                    $req->product_id = $row->product_id;
                    $req->price = (float) $cost['hpp_unit'];
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
            'invoice_penjualan_pos' => 0,
        ];

        $purchaseReceiveRepo = new PurchaseReceiveRepo(new PurchaseReceived(), app(ActivityLogService::class));
        PurchaseReceived::orderBy('receive_date', 'asc')->orderBy('id', 'asc')->chunk(200, function ($rows) use (&$summary, $purchaseReceiveRepo) {
            foreach ($rows as $row) {
                JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PENERIMAAN, $row->id);
                $purchaseReceiveRepo->postingJurnal($row->id);
                $summary['penerimaan']++;
            }
        });

        $purchaseInvoiceRepo = new PurchaseInvoiceRepo(new PurchaseInvoicing(), app(ActivityLogService::class));
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

        $salesDeliveryRepo = new SalesDeliveryRepo(new SalesDelivery(), app(ActivityLogService::class));
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
                    DB::table('als_jurnal_transactions')
                        ->where('transaction_code', TransactionsCode::INVOICE_PENJUALAN)
                        ->where('transaction_id', $row->id)
                        ->where(function ($q) {
                            $q->whereNull('note')->orWhereNotIn('note', ['Pembayaran POS', 'Pelunasan Piutang POS']);
                        })->delete();
                    $summary['invoice_penjualan_pos']++;
                } else {
                    JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::INVOICE_PENJUALAN, $row->id);
                }
                $salesInvoiceRepo->postingJurnal($row->id, true, true);
                $summary['invoice_penjualan']++;
            }
        });

        return $summary;
    }

    /** Global HPP expressed in the requested transaction unit. */
    public function movingAverageByDate($productId, $unitId, $date)
    {
        return $this->movingAverageSmallestByDate($productId, $date)
            * $this->getConversionFactorToSmallest($productId, $unitId);
    }

    public function getConversionFactorToSmallest($productId, $unitId): float
    {
        $product = Product::where('id', $productId)->first();
        if (empty($product) && self::$rebuildCreatedAt !== null) {
            throw new \RuntimeException("Produk sumber persediaan {$productId} tidak ditemukan.");
        }
        if (empty($product) || empty($unitId) || (string) $product->unit_id === (string) $unitId) {
            return 1;
        }

        $conversion = ProductConvertion::where([
            'product_id' => $productId,
            'unit_id' => $unitId
        ])->first();

        $factor = (float) ($conversion->nilai_terkecil ?? 0);
        if (!is_finite($factor) || $factor <= 0) {
            // Historical units may no longer exist in the current master.
            if (self::$rebuildInProgress) {
                return 1.0;
            }
            throw new \RuntimeException("Konversi satuan tidak valid: produk {$productId}, satuan {$unitId}.");
        }
        return $factor;
    }

    public function movingAverageSmallestByDate($productId, $date): float
    {
        $balance = Inventory::where('product_id', $productId)
            ->where('inventory_date', '<=', $this->inventoryDateCutoff($date))
            ->selectRaw('COALESCE(SUM(qty_in - qty_out), 0) AS qty, COALESCE(SUM(total_in - total_out), 0) AS value')
            ->first();
        $qty = (float) $balance->qty;
        if ($qty <= 0.00000001) {
            return $this->lastPositiveBalanceHpp($productId, $date) ?? 0.0;
        }
        return max(0, (float) $balance->value / $qty);
    }

    /** Keep the last average with positive stock; receipts while still in deficit must not change it. */
    private function lastPositiveBalanceHpp($productId, $date): ?float
    {
        $qty = 0.0;
        $value = 0.0;
        $lastHpp = null;
        $rows = Inventory::where('product_id', $productId)
            ->where('inventory_date', '<=', $this->inventoryDateCutoff($date))
            ->orderBy('inventory_date')->orderBy('created_at')->orderBy('id')
            ->cursor(['qty_in', 'qty_out', 'total_in', 'total_out']);
        foreach ($rows as $row) {
            $qty += (float) $row->qty_in - (float) $row->qty_out;
            $value += (float) $row->total_in - (float) $row->total_out;
            if ($qty > 0.00000001) {
                $lastHpp = max(0, $value / $qty);
            }
        }
        return $lastHpp;
    }

    private function inventoryDateCutoff($date): string
    {
        $date = (string) $date;
        return strlen($date) === 10 ? $date . ' 23:59:59' : $date;
    }

    public function resolveOutgoingCost($productId, $unitId, $qty, $date): array
    {
        $factor = $this->getConversionFactorToSmallest($productId, $unitId);
        $qtySmallest = (float) $qty * $factor;
        $hppSmallest = $this->movingAverageSmallestByDate($productId, $date);

        return [
            'factor' => $factor,
            'qty_smallest' => $qtySmallest,
            'hpp_smallest' => $hppSmallest,
            'hpp_unit' => $hppSmallest * $factor,
            'subtotal_hpp' => $hppSmallest * $qtySmallest,
        ];
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
        $res = Inventory::where(array('transaction_code' => $transactionCode, 'transaction_id' => $idTransaction, 'transaction_sub_id' => $transaction_sub_id))->first();
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
