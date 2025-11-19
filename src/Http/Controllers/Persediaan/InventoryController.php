<?php


namespace Icso\Accounting\Http\Controllers\Persediaan;


use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\KartuStokExport;
use Icso\Accounting\Models\Akuntansi\SaldoAwal;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Repositories\Master\Product\ProductRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Utils\Constants;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
class InventoryController extends Controller
{
    protected $inventoryRepo;

    public function __construct(InventoryRepo $inventoryRepo)
    {
        $this->inventoryRepo = $inventoryRepo;
    }

    public function getStockHppByDate(Request $request)
    {
        $stockDate = $request->stock_date;
        $warehouseId = $request->warehouse_id;
        $productId = $request->product_id;
        $unitId = $request->unit_id;
        $stock = $this->inventoryRepo->getStokByDate($productId,$warehouseId,$unitId,$stockDate);
        $hpp = $this->inventoryRepo->movingAverageByDate($productId,$unitId,$stockDate);
        $this->data['status'] = true;
        $this->data['data'] = array(
            'qty' => $stock,
            'value' => $hpp,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId
        );
        $this->data['message'] = 'Data berhasil load';
        return response()->json($this->data);
    }

    public function getAllStockAwal(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perPage = $request->perpage;
        $inputType = $request->input_type;
        $coaId = $request->coa_id;
        $data = $this->inventoryRepo->getAllDataStockAwalBy($search,$page, $perPage, array(StockAwal::getTableName().'.coa_id' => $coaId));
        $total = $this->inventoryRepo->getAllTotalDataStockAwalBy($search,array(StockAwal::getTableName().'.coa_id' => $coaId));
        if(count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['total'] = $total;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function updateAwal(Request $request){
        $id = $request->id;
        $productId = $request->product_id;
        $unitId = $request->unit_id;
        $qty = $request->qty;
        $warehouseId = $request->warehouse_id;
        $nominal = $request->nominal;
        $total = $request->total;
        $userId = $request->user_id;
        DB::beginTransaction();
        try {
            $arrStockAwal = array(
                'product_id' => $productId,
                'qty' => $qty,
                'unit_id' => $unitId,
                'warehouse_id' => $warehouseId,
                'total' => Utility::remove_commas($total),
                'nominal' => Utility::remove_commas($nominal),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $userId,
            );
            $resStock = StockAwal::where(array('id' => $id))->update($arrStockAwal);
            $price = $nominal;
            if (!empty($qty)) {
                $findProduct = Product::where(array('id' => $productId))->first();
                if (!empty($findProduct)) {
                    if ($findProduct->unit_id != $unitId) {
                        $findConvertion = ProductConvertion::where(array('product_id' => $productId, 'unit_id' => $unitId))->first();
                        if (!empty($findConvertion)) {
                            $nilai = $findConvertion->nilai;
                            $qty = $qty * $nilai;
                            $price = $nominal / $nilai;
                        }
                    }
                }
            }
            $totalIn = $price * $qty;
            $arrData = array(
                'qty_in' => $qty,
                'nominal' => $price,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'unit_id' => $unitId,
                'total_in' => $totalIn,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $userId,
            );
            Inventory::where(array('transaction_code' => TransactionsCode::SALDO_AWAL, 'transaction_id' => $id))->update($arrData);
            DB::commit();
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        } catch (\Exception $e) {
            DB::rollBack();
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
        }
        return response()->json($this->data);
    }

    public function storeAwal(Request $request){
        $coaId = $request->coa_id;
        $userId = $request->user_id;
        $stock = json_decode(json_encode($request->stock));
        DB::beginTransaction();
        try {
            if (count($stock) > 0) {
                $findSaldoAwalData = SaldoAwal::where(array('is_default' => '1'))->first();
                $saldoAwalDate = date('Y-m-d H:i:s');
                if(!empty($findSaldoAwalData)){
                    $saldoAwalDate = $findSaldoAwalData->saldo_date." ".date('H:i:s');
                }
                foreach ($stock as $i => $item) {
                    $req = new Request();
                    $req->coa_id = $coaId;
                    $req->user_id = $userId;
                    $req->inventory_date = $saldoAwalDate;
                    $req->transaction_code = TransactionsCode::SALDO_AWAL;
                    $req->qty_in = $item->qty_in;
                    $req->warehouse_id = $item->warehouse_id;
                    $req->product_id = $item->product_id;
                    $req->price = $item->nominal;
                    $req->note = $item->note;
                    $req->unit_id = $item->unit_id;
                    $arrStockAwal = array(
                        'stock_date' => $saldoAwalDate,
                        'product_id' => $item->product_id,
                        'qty' => $item->qty_in,
                        'unit_id' => $item->unit_id,
                        'warehouse_id' => $item->warehouse_id,
                        'total' => Utility::remove_commas($item->total_in),
                        'coa_id' => $coaId,
                        'nominal' => Utility::remove_commas($item->nominal),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'updated_by' => $userId,
                        'created_by' => $userId
                    );
                    $resStock = StockAwal::create($arrStockAwal);
                    $req->transaction_id = $resStock->id;
                    $this->inventoryRepo->store($req);
                }
            }
            DB::commit();
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        }catch (\Exception $e) {
            DB::rollBack();
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
        }
        return response()->json($this->data);
    }

    public function deleteSaldoAwal(Request $request){

    }

    public function getStockByProductId(Request $request)
    {
        $productId = $request->product_id;
        $page = $request->page;
        $perPage = $request->perpage;
        $data = $this->inventoryRepo->getAllDataBy("",$page,$perPage,array('product_id' => $productId));
        $total = $this->inventoryRepo->getAllTotalDataBy("",array('product_id' => $productId));
        if(count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['total'] = $total;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function kartuStok(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $productId = $request->product_id;
        $warehouseId = $request->warehouse_id;
        $fromDate = !empty($request->from_date) ? $request->from_date : date('Y-m-d');
        $untilDate = !empty($request->until_date) ? $request->until_date : Utility::lastDateMonth();
        $productRepo = new ProductRepo(new Product());

        $where=array('product_type' => ProductType::ITEM);
        if(!empty($productId)){
            $where[] = ['id','=',$productId];
        }
        $processedResults = collect();
        $findAll = $productRepo->getAllDataProduct($search, $where)->chunk(200, function ($input) use ($fromDate,$untilDate,$warehouseId, &$processedResults) {
            $processedInventory = $input->map(function ($product) use ($fromDate, $untilDate,$warehouseId) {
                $saldoAwalStok = InventoryRepo::getStokBy($product->id,$warehouseId,$fromDate, $untilDate,"<");
                $saldoAwalNilaiStok = InventoryRepo::getStokValueBy($product->id,$warehouseId,$fromDate, $untilDate,"<");
                $saldoAwal = $saldoAwalStok['total'];
                $saldoAwalNilai = $saldoAwalNilaiStok['total'];
                $getCurrentStok = InventoryRepo::getStokBy($product->id,$warehouseId,$fromDate, $untilDate);
                $addStock = $getCurrentStok['qty_in'];
                $subStock = $getCurrentStok['qty_out'];
                $saldoAkhir = $saldoAwal + ($addStock - $subStock);
                $product->saldo_awal = $saldoAwal;
                $product->saldo_awal_nilai = $saldoAwalNilai;
                $product->qty_in = $addStock;
                $product->qty_out = $subStock;
                $product->saldo_akhir = $saldoAkhir;
                return $product;
            });
            $processedResults = $processedResults->concat($processedInventory);
        });
        $paginateProduct = $processedResults->forPage($page,$perpage)->values()->toArray();
        $totalRecords = $processedResults->count();
        if($findAll)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $paginateProduct;
            $this->data['has_more'] = false;
            $this->data['total'] = $totalRecords;
        } else{
            $this->data['status'] = false;
            $this->data['data'] = [];
            $this->data['has_more'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function showKartuStockDetail(Request $request){
        $page = $request->page;
        $perpage = $request->perpage;
        $productId = $request->product_id;
        $warehouseId = $request->warehouse_id;
        $fromDate = !empty($request->from_date) ? $request->from_date : date('Y-m-d');
        $untilDate = !empty($request->until_date) ? $request->until_date : Utility::lastDateMonth();
        $where = array('product_id' => $productId);
        if(!empty($warehouseId)){
            $where[] = ['warehouse_id', '=', $warehouseId];
        }
        $processedResults = collect();
        $resultInventory = Inventory::where($where)->whereBetween('inventory_date',[$fromDate,$untilDate])->chunk(200, function ($input) use (&$processedResults) {
            $processedInventory = $input->map(function ($inventory) {
                $findTransaction = TransactionsCode::getNumberAndNameTransaction($inventory->transaction_code, $inventory->transaction_id);
                $inventory->transaction_name = $findTransaction['transaction_name'];
                $inventory->transaction_no = $findTransaction['transaction_no'];
                return $inventory;
            });
            $processedResults = $processedResults->concat($processedInventory);
        });
        $paginateInventory = $processedResults->forPage($page,$perpage)->values()->toArray();
        $totalCount = $processedResults->count();
        if($paginateInventory)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $paginateInventory;
            $this->data['total'] = $totalCount;
        } else{
            $this->data['status'] = false;
            $this->data['data'] = [];
            $this->data['total'] = 0;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function exportKartuStokExcel(Request $request)
    {
        return Excel::download(
            new KartuStokExport(
                $request->product_id,      // boleh kosong
                $request->warehouse_id,
                $request->from_date ?? date('Y-m-d'),
                $request->until_date ?? Utility::lastDateMonth()
            ),
            'kartu_stok_detail.xlsx'
        );
    }

    public function exportKartuStokPdf(Request $request)
    {
        $search = $request->q;
        $productId = $request->product_id;
        $warehouseId = $request->warehouse_id;

        $fromDate = $request->from_date ?? date('Y-m-d');
        $untilDate = $request->until_date ?? Utility::lastDateMonth();

        // === Ambil data produk ===
        $productRepo = new ProductRepo(new Product());

        $where = ['product_type' => ProductType::ITEM];
        if (!empty($productId)) {
            $where[] = ['id', '=', $productId];
        }

        $products = $productRepo->getAllDataProduct($search, $where)->get();

        $summary = [];

        foreach ($products as $product) {

            // Saldo awal
            $saldoAwal = InventoryRepo::getStokBy(
                $product->id,
                $warehouseId,
                $fromDate,
                $untilDate,
                "<"
            )['total'];

            // Nilai masuk & keluar selama periode
            $current = InventoryRepo::getStokBy(
                $product->id,
                $warehouseId,
                $fromDate,
                $untilDate
            );

            $qtyIn = $current['qty_in'];
            $qtyOut = $current['qty_out'];

            // Saldo akhir
            $saldoAkhir = $saldoAwal + ($qtyIn - $qtyOut);

            $summary[] = [
                'product_name' => $product->product_name,
                'product_code' => $product->product_code,
                'saldo_awal'   => $saldoAwal,
                'qty_in'       => $qtyIn,
                'qty_out'      => $qtyOut,
                'saldo_akhir'  => $saldoAkhir,
            ];
        }

        $pdf = Pdf::loadView('accounting::stock.kartu_stok_summary_pdf', [
            'summary' => $summary,
            'fromDate' => $fromDate,
            'untilDate' => $untilDate,
        ])->setPaper('A4', 'landscape');

        return $pdf->download('kartu_stok_ringkasan.pdf');
    }

    public function inventoryKpis(Request $request)
    {
        $fromDateAll = Inventory::min('inventory_date') ?? date('Y-01-01');
        $untilToday = date('Y-m-d');
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

        $products = Product::where('item_status', Constants::AKTIF)->get();

        $totalInventoryValue = 0;
        $lowStockItems = 0;
        $outOfStockItems = 0;

        $warehouseValues = [];

        foreach ($products as $product) {

            // ==== SALDO AWAL ====
            $saldoAwalQty = InventoryRepo::getStokBy(
                $product->id,
                null,
                $fromDateAll,
                $untilToday,
                "<"
            )['total'];

            $saldoAwalNilai = InventoryRepo::getStokValueBy(
                $product->id,
                null,
                $fromDateAll,
                $untilToday,
                "<"
            )['total'];

            // ==== PERGERAKAN HINGGA HARI INI ====
            $current = InventoryRepo::getStokBy(
                $product->id,
                null,
                $fromDateAll,
                $untilToday
            );

            $currentValue = InventoryRepo::getStokValueBy(
                $product->id,
                null,
                $fromDateAll,
                $untilToday
            );

            // SALDO AKHIR
            $saldoAkhirQty = $saldoAwalQty + ($current['qty_in'] - $current['qty_out']);
            $saldoAkhirNilai = $saldoAwalNilai + ($currentValue['value_in'] - $currentValue['value_out']);

            // TOTAL NILAI PERSEDIAAN
            $totalInventoryValue += $saldoAkhirNilai;

            // Barang Hampir Habis
            if ($saldoAkhirQty < $product->minimum_stock) {
                $lowStockItems++;
            }

            // Barang Habis
            if ($saldoAkhirQty == 0) {
                $outOfStockItems++;
            }

            // ==== NILAI PER GUDANG ====
            foreach (Warehouse::all() as $wh) {

                // Saldo awal nilai per gudang
                $awalNilai = InventoryRepo::getStokValueBy(
                    $product->id,
                    $wh->id,
                    $fromDateAll,
                    $untilToday,
                    "<"
                )['total'];

                // Nilai mutasi per gudang
                $currVal = InventoryRepo::getStokValueBy(
                    $product->id,
                    $wh->id,
                    $fromDateAll,
                    $untilToday
                );

                $saldoNilaiGudang =
                    $awalNilai + ($currVal['value_in'] - $currVal['value_out']);

                if (!isset($warehouseValues[$wh->id])) {
                    $warehouseValues[$wh->id] = [
                        'warehouse_id' => $wh->id,
                        'warehouse_name' => $wh->warehouse_name,
                        'total_value' => 0
                    ];
                }

                $warehouseValues[$wh->id]['total_value'] += $saldoNilaiGudang;
            }
        }

        // ==== SLOW MOVING (tidak bergerak 30 hari) ====
        $slowMoving = Product::whereNotExists(function ($q) use ($thirtyDaysAgo) {
            $q->select(DB::raw(1))
                ->from('als_inventory')
                ->whereColumn('als_inventory.product_id', 'als_product.id')
                ->where('inventory_date', '>=', $thirtyDaysAgo);
        })->count();

        // ==== FAST MOVING ====
        $fastMoving = Inventory::where('inventory_date', '>=', $thirtyDaysAgo)
            ->where('qty_out', '>', 0)
            ->groupBy('product_id')
            ->count();


        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil KPI persediaan',
            'data' => [
                'total_inventory_value' => $totalInventoryValue,
                'total_active_products' => $products->count(),
                'low_stock_items' => $lowStockItems,
                'out_of_stock_items' => $outOfStockItems,
                'inventory_value_by_warehouse' => array_values($warehouseValues),
                'slow_moving_items' => $slowMoving,
                'fast_moving_items' => $fastMoving
            ]
        ]);
    }
}
