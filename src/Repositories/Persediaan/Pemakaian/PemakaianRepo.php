<?php

namespace Icso\Accounting\Repositories\Persediaan\Pemakaian;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockUsage;
use Icso\Accounting\Models\Persediaan\StockUsageMeta;
use Icso\Accounting\Models\Persediaan\StockUsageProduct;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PemakaianRepo extends ElequentRepository
{
    protected $model;

    public function __construct(StockUsage $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'], $item['value']['value']);
                    } else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
        })->orderBy('usage_date','desc')->with(['warehouse','coa_stock','stockusageproduct','stockusageproduct.product','stockusageproduct.coa','stockusageproduct.unit'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'], $item['value']['value']);
                    } else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
        })->orderBy('usage_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;
        $arrData = $this->prepareData($request);

        DB::beginTransaction();
        try {
            $res = $this->saveData($arrData, $id, $userId);

            if ($res) {
                $idUsage = empty($id) ? $res->id : $id;

                $this->handleProducts($request, $idUsage);
                $this->postingJurnal($idUsage);
                $this->handleFileUploads($request->file('files'), $idUsage, $userId);

                DB::commit();
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function prepareData($request)
    {
        $refNo = !empty($request->ref_no) ? $request->ref_no : self::generateCodeTransaction(new StockUsage(), KeyNomor::NO_PEMAKAIAN_STOCK, 'ref_no', 'usage_date');
        $usageDate = !empty($request->usage_date) ? Utility::changeDateFormat($request->usage_date) : date('Y-m-d');

        return [
            'ref_no' => $refNo,
            'usage_date' => $usageDate,
            'note' => !empty($request->note) ? $request->note : '',
            'warehouse_id' => !empty($request->warehouse_id) ? $request->warehouse_id : '0',
            'document' => !empty($request->document) ? $request->document : '',
            'coa_id' => !empty($request->coa_id) ? $request->coa_id : '0',
            'updated_by' => $request->user_id,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    public function saveData($arrData, $id, $userId)
    {
        if (empty($id)) {
            $arrData['status_usage'] = StatusEnum::SELESAI;
            $arrData['reason'] = "";
            $arrData['created_at'] = date('Y-m-d H:i:s');
            $arrData['created_by'] = $userId;
            return $this->create($arrData);
        } else {
            return $this->update($arrData, $id);
        }
    }

    public function handleProducts($request, $idUsage)
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if (!empty($request->stockusageproduct)) {
            if (is_array($request->stockusageproduct)) {
                $products = json_decode(json_encode($request->stockusageproduct));
            } else {
                $products = $request->stockusageproduct;
            }
            $this->usageProducts($products, $idUsage);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function usageProducts($products, $idUsage)
    {
        if (count($products) > 0) {
            foreach ($products as $item) {
                $arrItem = [
                    'qty' => $item->qty,
                    'hpp' => 0,
                    'subtotal' => 0,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'coa_id' => $item->coa_id,
                    'usage_stock_id' => $idUsage,
                    'note' => $item->note,
                ];
                StockUsageProduct::create($arrItem);
            }
        }
    }

    private function handleFileUploads($uploadedFiles, $idUsage, $userId)
    {
        if (!empty($uploadedFiles) && count($uploadedFiles) > 0) {
            $fileUpload = new FileUploadService();

            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    $arrUpload = [
                        'usage_stock_id' => $idUsage,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ];
                    StockUsageMeta::create($arrUpload);
                }
            }
        }
    }

    public function deleteAdditional($id)
    {
        StockUsageProduct::where(array('usage_stock_id' => $id))->delete();
        StockUsageMeta::where(array('usage_stock_id' => $id))->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PEMAKAIAN_STOCK, $id);
        Inventory::where(array('transaction_code' => TransactionsCode::PEMAKAIAN_STOCK, 'transaction_id' => $id))->delete();

    }

    public function postingJurnal($idStockUsage)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $inventoryRepo = new InventoryRepo(new Inventory());
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $find = $this->findOne($idStockUsage,array(),['warehouse','coa_stock','stockusageproduct','stockusageproduct.product','stockusageproduct.coa','stockusageproduct.unit']);
        if(!empty($find))
        {
            $usageDate = $find->usage_date;
            $refNo = $find->ref_no;
            if(!empty($find->stockusageproduct)) {
                $usageProduct = $find->stockusageproduct;
                if (count($usageProduct) > 0) {
                    foreach ($usageProduct as $item) {
                        $product = $item->product;
                        $productName = "";
                        if (!empty($product)) {
                            if (!empty($product->coa_id)) {
                                $coaSediaan = $product->coa_id;

                            }
                            $productName = $product->item_name;
                        }
                        $noteProduct = !empty($productName) ? " dengan nama " . $productName : "";
                        $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $usageDate);
                        $subtotal = $hpp * $item->qty;
                        $arrUpdateItem = array(
                            'subtotal' => $subtotal,
                            'hpp' => $hpp
                        );
                        StockUsageProduct::where(array('id' => $item->id))->update($arrUpdateItem);
                        $reqInventory = new Request();
                        $reqInventory->coa_id = $coaSediaan;
                        $reqInventory->user_id = $find->created_by;
                        $reqInventory->inventory_date = $usageDate;
                        $reqInventory->transaction_code = TransactionsCode::PEMAKAIAN_STOCK;
                        $reqInventory->transaction_id = $find->id;
                        $reqInventory->transaction_sub_id = $item->id;
                        $reqInventory->qty_out = $item->qty;
                        $reqInventory->warehouse_id = $find->warehouse_id;
                        $reqInventory->product_id = $item->product_id;
                        $reqInventory->price = $hpp;
                        $reqInventory->note = $find->note;
                        $reqInventory->unit_id = $item->unit_id;
                        $inventoryRepo->store($reqInventory);

                        $arrJurnalDebet = array(
                            'transaction_date' => $usageDate,
                            'transaction_datetime' => $usageDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::PEMAKAIAN_STOCK,
                            'coa_id' => $item->coa_id,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $item->id,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $refNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $subtotal,
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Pemakaian Barang'.$noteProduct,
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);

                        $arrJurnalKredit = array(
                            'transaction_date' => $usageDate,
                            'transaction_datetime' => $usageDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::PEMAKAIAN_STOCK,
                            'coa_id' => $coaSediaan,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $item->id,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $refNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => 0,
                            'kredit' => $subtotal,
                            'note' => !empty($find->note) ? $find->note : 'Pemakaian Barang'.$noteProduct,
                        );
                        $jurnalTransaksiRepo->create($arrJurnalKredit);
                    }
                }
            }
        }
    }
}
