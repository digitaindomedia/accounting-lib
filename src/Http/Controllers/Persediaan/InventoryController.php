<?php


namespace Icso\Accounting\Http\Controllers\Persediaan;


use Icso\Accounting\Models\Akuntansi\SaldoAwal;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Repositories\Master\Product\ProductRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
