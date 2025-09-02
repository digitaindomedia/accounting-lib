<?php

namespace Icso\Accounting\Repositories\Persediaan\Inventory\Interface;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Repositories\ElequentRepository;
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
        $qtyIn = !empty($request->qty_in) ? $request->qty_in : 0;
        $qtyOut = !empty($request->qty_out) ? $request->qty_out : 0;
        $coaId = !empty($request->coa_id) ? $request->coa_id : 0;
        $warehouseId = !empty($request->warehouse_id) ? $request->warehouse_id : '0';
        $productId = !empty($request->product_id) ? $request->product_id : '0';
        $note = !empty($request->note) ? $request->note : '';
        $price =Utility::remove_commas($request->price);
        $unitId = !empty($request->unit_id) ? $request->unit_id : 0;


        if(!empty($qtyIn)){
            $findProduct = Product::where(array('id' => $productId))->first();
            if(!empty($findProduct)){
                if($findProduct->unit_id != $unitId){
                    $findConvertion = ProductConvertion::where(array('product_id' => $productId, 'unit_id' => $unitId))->first();
                    if(!empty($findConvertion)){
                        $nilai = $findConvertion->nilai_terkecil;
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
                        $nilai = $findConvertion->nilai_terkecil;
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
            'tota_out' => $totalOut,
            'total' => $total
        );
    }
}
