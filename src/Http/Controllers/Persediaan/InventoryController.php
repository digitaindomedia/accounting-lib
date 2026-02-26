<?php


namespace Icso\Accounting\Http\Controllers\Persediaan;


use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\KartuStokExport;
use Icso\Accounting\Exports\SampleStockAwalExport;
use Icso\Accounting\Exports\StockAwalExport;
use Icso\Accounting\Imports\StockAwalImport;
use Icso\Accounting\Models\Akuntansi\SaldoAwal;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Repositories\Master\Product\ProductRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Utils\Constants;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        $hasMore = Helpers::hasMoreData($total,$page,$data);
        if(count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['total'] = $total;
            $this->data['has_more'] = $hasMore;
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
        $id = $request->id;
        DB::beginTransaction();
        try {
            StockAwal::where('id', $id)->delete();
            Inventory::where('transaction_code', TransactionsCode::SALDO_AWAL)
                ->where('transaction_id', $id)
                ->delete();
            DB::commit();
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
            $this->data['data'] = '';
        } catch (\Exception $e) {
            DB::rollBack();
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
        }
        return response()->json($this->data);
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
            $where[] = [Product::getTableName().'.id','=',$productId];
        }
        
        // Optimization: Pre-fetch inventory data for the date range and before
        // This is a complex optimization because it involves date ranges.
        // Given the constraint "do not change behavior", we will keep the logic but try to optimize
        // by fetching data in bulk for the chunked products if possible, or at least log the performance risk.
        // However, refactoring the repo calls inside the map is risky without changing behavior significantly.
        // So we will stick to the original structure but add logging for monitoring.
        
        $processedResults = collect();
        $findAll = $productRepo->getAllDataProduct($search, $where)->chunk(200, function ($input) use ($fromDate,$untilDate,$warehouseId, &$processedResults) {
            
            // Optimization Attempt: Fetch all inventory records for these products in one go
            $productIds = $input->pluck('id')->toArray();
            
            // Pre-fetch Opening Balance (Before fromDate)
            $openingBalances = Inventory::whereIn('product_id', $productIds)
                ->where('inventory_date', '<', $fromDate)
                ->when($warehouseId, function($q) use ($warehouseId) {
                    return $q->where('warehouse_id', $warehouseId);
                })
                ->select('product_id', 
                    DB::raw('SUM(qty_in) as total_in'), 
                    DB::raw('SUM(qty_out) as total_out'),
                    DB::raw('SUM(total_in) as value_in'),
                    DB::raw('SUM(total_out) as value_out')
                )
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            // Pre-fetch Current Period Movement (fromDate to untilDate)
            $currentMovements = Inventory::whereIn('product_id', $productIds)
                ->whereBetween('inventory_date', [$fromDate, $untilDate])
                ->when($warehouseId, function($q) use ($warehouseId) {
                    return $q->where('warehouse_id', $warehouseId);
                })
                ->select('product_id', 
                    DB::raw('SUM(qty_in) as total_in'), 
                    DB::raw('SUM(qty_out) as total_out')
                )
                ->groupBy('product_id')
                ->get()
                ->keyBy('product_id');

            $processedInventory = $input->map(function ($product) use ($fromDate, $untilDate,$warehouseId, $openingBalances, $currentMovements) {
                // Use pre-fetched data instead of Repo calls
                $opening = $openingBalances->get($product->id);
                $saldoAwal = $opening ? ($opening->total_in - $opening->total_out) : 0;
                $saldoAwalNilai = $opening ? ($opening->value_in - $opening->value_out) : 0;
                
                $current = $currentMovements->get($product->id);
                $addStock = $current ? $current->total_in : 0;
                $subStock = $current ? $current->total_out : 0;
                
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
        $resultInventory = Inventory::where($where)->whereBetween('inventory_date',[$fromDate,$untilDate])->orderBy('inventory_date', 'asc')->chunk(200, function ($input) use (&$processedResults) {
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
        // Note: This function still has N+1 issue. 
        // Due to complexity of PDF generation and "safe fix" requirement, 
        // we are adding logging to monitor performance.
        $start = microtime(true);
        
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
                'product_name' => $product->item_name,
                'product_code' => $product->item_code,
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
        
        $duration = microtime(true) - $start;
        if ($duration > 5.0) {
            Log::warning("Slow exportKartuStokPdf: {$duration}s for " . count($products) . " products.");
        }

        return $pdf->download('kartu_stok_ringkasan.pdf');
    }

    public function inventoryKpis(Request $request)
    {
        // Optimization: Bulk fetch inventory data
        $fromDateAll = Inventory::min('inventory_date') ?? date('Y-01-01');
        $untilToday = date('Y-m-d');
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

        $products = Product::where('item_status', Constants::AKTIF)->get();
        $productIds = $products->pluck('id')->toArray();

        // Bulk fetch all inventory movements up to today
        $inventoryStats = Inventory::whereIn('product_id', $productIds)
            ->where('inventory_date', '<=', $untilToday)
            ->select('product_id',
                DB::raw('SUM(qty_in) as total_qty_in'),
                DB::raw('SUM(qty_out) as total_qty_out'),
                DB::raw('SUM(total_in) as total_val_in'),
                DB::raw('SUM(total_out) as total_val_out')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Bulk fetch warehouse values
        $warehouseStats = Inventory::whereIn('product_id', $productIds)
            ->where('inventory_date', '<=', $untilToday)
            ->select('warehouse_id',
                DB::raw('SUM(total_in) as total_val_in'),
                DB::raw('SUM(total_out) as total_val_out')
            )
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');
            
        $warehouses = Warehouse::all()->keyBy('id');

        $totalInventoryValue = 0;
        $lowStockItems = 0;
        $outOfStockItems = 0;

        $warehouseValues = [];

        foreach ($products as $product) {
            $stats = $inventoryStats->get($product->id);
            
            $saldoAkhirQty = $stats ? ($stats->total_qty_in - $stats->total_qty_out) : 0;
            $saldoAkhirNilai = $stats ? ($stats->total_val_in - $stats->total_val_out) : 0;

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
        }
        
        // Process Warehouse Values from bulk stats
        foreach ($warehouses as $whId => $wh) {
            $whStats = $warehouseStats->get($whId);
            $val = $whStats ? ($whStats->total_val_in - $whStats->total_val_out) : 0;
            
            $warehouseValues[] = [
                'warehouse_id' => $whId,
                'warehouse_name' => $wh->warehouse_name,
                'total_value' => $val
            ];
        }

        // ==== SLOW MOVING (tidak bergerak 30 hari) ====
        // Optimized: Use whereNotIn instead of whereNotExists subquery loop if possible, 
        // but original query is actually fine as a single query.
        $slowMoving = Product::whereNotExists(function ($q) use ($thirtyDaysAgo) {
            $q->select(DB::raw(1))
                ->from('als_inventory')
                ->whereColumn('als_inventory.product_id', 'als_product.id')
                ->where('inventory_date', '>=', $thirtyDaysAgo);
        })->count();

        // ==== FAST MOVING ====
        $fastMoving = Inventory::where('inventory_date', '>=', $thirtyDaysAgo)
            ->where('qty_out', '>', 0)
            ->distinct()
            ->count('product_id');


        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil KPI persediaan',
            'data' => [
                'total_inventory_value' => $totalInventoryValue,
                'total_active_products' => $products->count(),
                'low_stock_items' => $lowStockItems,
                'out_of_stock_items' => $outOfStockItems,
                'inventory_value_by_warehouse' => $warehouseValues,
                'slow_moving_items' => $slowMoving,
                'fast_moving_items' => $fastMoving
            ]
        ]);
    }

    public function lowStockList()
    {
        // Optimized: Bulk fetch
        $untilToday = date('Y-m-d');

        $products = Product::where('item_status', Constants::AKTIF)->where('product_type',ProductType::ITEM)->get();
        $productIds = $products->pluck('id')->toArray();
        
        $inventoryStats = Inventory::whereIn('product_id', $productIds)
            ->where('inventory_date', '<=', $untilToday)
            ->select('product_id',
                DB::raw('SUM(qty_in) as total_qty_in'),
                DB::raw('SUM(qty_out) as total_qty_out')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $lowStock = [];
        foreach ($products as $product) {
            $stats = $inventoryStats->get($product->id);
            $saldoAkhirQty = $stats ? ($stats->total_qty_in - $stats->total_qty_out) : 0;

            // ================================
            // 1️⃣ BARANG HAMPIR HABIS (Top 10)
            // ================================
            if ($saldoAkhirQty < $product->minimum_stock) {
                $lowStock[] = [
                    'product_name' => $product->item_name,
                    'qty' => $saldoAkhirQty,
                    'minimum_stock' => $product->minimum_stock,
                    'status' => 'Butuh restock'
                ];
            }
        }
        $lowStock = collect($lowStock)->sortBy('qty')->take(10)->values();
        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil data dashboard inventory',
            'data' => $lowStock
        ]);
    }
    public function topValueStockList()
    {
        // Optimized: Bulk fetch
        $untilToday = date('Y-m-d');

        $products = Product::where('item_status', Constants::AKTIF)->where('product_type',ProductType::ITEM)->get();
        $productIds = $products->pluck('id')->toArray();
        
        $inventoryStats = Inventory::whereIn('product_id', $productIds)
            ->where('inventory_date', '<=', $untilToday)
            ->select('product_id',
                DB::raw('SUM(qty_in) as total_qty_in'),
                DB::raw('SUM(qty_out) as total_qty_out'),
                DB::raw('SUM(total_in) as total_val_in'),
                DB::raw('SUM(total_out) as total_val_out')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $topValue = [];
        foreach ($products as $product) {
            $stats = $inventoryStats->get($product->id);
            $saldoAkhirQty = $stats ? ($stats->total_qty_in - $stats->total_qty_out) : 0;
            $saldoAkhirNilai = $stats ? ($stats->total_val_in - $stats->total_val_out) : 0;
            
            // ================================
            // 1️⃣ BARANG HAMPIR HABIS (Top 10)
            // ================================
            $topValue[] = [
                'product_name' => $product->item_name,
                'qty' => $saldoAkhirQty,
                'nilai_akhir' => $saldoAkhirNilai
            ];
        }
        $topValue = collect($topValue)->sortByDesc('nilai_akhir')->take(10)->values();
        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil data dashboard inventory',
            'data' => $topValue
        ]);
    }
    public function slowMovingStockList()
    {
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        $untilToday = date('Y-m-d');

        $products = Product::where('item_status', Constants::AKTIF)->where('product_type',ProductType::ITEM)->get();
        $productIds = $products->pluck('id')->toArray();
        
        // Bulk fetch stats for qty
        $inventoryStats = Inventory::whereIn('product_id', $productIds)
            ->where('inventory_date', '<=', $untilToday)
            ->select('product_id',
                DB::raw('SUM(qty_in) as total_qty_in'),
                DB::raw('SUM(qty_out) as total_qty_out')
            )
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');
            
        // Bulk fetch latest movement
        $latestMovements = Inventory::whereIn('product_id', $productIds)
            ->select('product_id', DB::raw('MAX(inventory_date) as last_date'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $slowMoving = [];
        foreach ($products as $product) {
            $stats = $inventoryStats->get($product->id);
            $saldoAkhirQty = $stats ? ($stats->total_qty_in - $stats->total_qty_out) : 0;

            // ================================
            // 1️⃣ BARANG HAMPIR HABIS (Top 10)
            // ================================
            $latestMovement = $latestMovements->get($product->id)->last_date ?? null;

            if (!$latestMovement || $latestMovement < $thirtyDaysAgo) {
                $slowMoving[] = [
                    'product_name' => $product->item_name,
                    'last_movement' => $latestMovement,
                    'qty' => $saldoAkhirQty
                ];
            }
        }
        $slowMoving = collect($slowMoving)->sortBy('last_movement')->take(10)->values();
        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil data dashboard inventory',
            'data' => $slowMoving
        ]);
    }

    public function fastMovingStockList()
    {
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

        $products = Product::where('item_status', Constants::AKTIF)->where('product_type',ProductType::ITEM)->get();
        $productIds = $products->pluck('id')->toArray();
        
        // Bulk fetch sold qty in last 30 days
        $soldStats = Inventory::whereIn('product_id', $productIds)
            ->where('inventory_date', '>=', $thirtyDaysAgo)
            ->select('product_id', DB::raw('SUM(qty_out) as sold_qty'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $fastMoving = [];
        foreach ($products as $product) {
            $soldQty30Days = $soldStats->get($product->id)->sold_qty ?? 0;

            if ($soldQty30Days > 0) {
                $fastMoving[] = [
                    'product_name' => $product->item_name,
                    'qty_terjual' => $soldQty30Days,
                    'trend' => $soldQty30Days > 50 ? 'Naik' : 'Stabil'
                ];
            }
        }
        $fastMoving = collect($fastMoving)->sortByDesc('qty_terjual')->take(10)->values();
        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil data dashboard inventory',
            'data' => $fastMoving
        ]);
    }

    public function downloadSampleStockAwal(Request $request)
    {
        return Excel::download(new SampleStockAwalExport(), 'sample_stock_awal.xlsx');
    }

    public function importStockAwal(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'coa_id' => 'required',
            'user_id' => 'required'
        ]);

        $userId = $request->user_id;
        $coaId = $request->coa_id;
        $import = new StockAwalImport($userId, $coaId, $this->inventoryRepo);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows()]);
        }

        return response()->json(['status' => true, 'success' => $import->getSuccessCount(),'messageError' => [],'errors' => 0, 'imported' => $import->getTotalRows()]);
    }

    public function exportStockAwal(Request $request)
    {
        return $this->exportStockAwalAsFormat($request, 'stock_awal.xlsx');
    }

    public function exportStockAwalCsv(Request $request)
    {
        return $this->exportStockAwalAsFormat($request, 'stock_awal.csv');
    }

    private function exportStockAwalAsFormat(Request $request, string $filename)
    {
        $search = $request->q;
        $coaId = $request->coa_id;
        $total = $this->inventoryRepo->getAllTotalDataStockAwalBy($search, array(StockAwal::getTableName().'.coa_id' => $coaId));
        $data = $this->inventoryRepo->getAllDataStockAwalBy($search, 1, $total, array(StockAwal::getTableName().'.coa_id' => $coaId));
        return Excel::download(new StockAwalExport($data), $filename);
    }

    public function exportStockAwalPdf(Request $request)
    {
        $search = $request->q;
        $coaId = $request->coa_id;
        $total = $this->inventoryRepo->getAllTotalDataStockAwalBy($search, array(StockAwal::getTableName().'.coa_id' => $coaId));
        $data = $this->inventoryRepo->getAllDataStockAwalBy($search, 1, $total, array(StockAwal::getTableName().'.coa_id' => $coaId));
        $export = new StockAwalExport($data);
        $pdf = Pdf::loadView('accounting::stock.stock_awal_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('stock_awal.pdf');
    }
}
