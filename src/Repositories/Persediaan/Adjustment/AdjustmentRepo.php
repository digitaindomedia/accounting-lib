<?php

namespace Icso\Accounting\Repositories\Persediaan\Adjustment;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Jurnal\JurnalTransaksi;
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Models\Persediaan\AdjustmentMeta;
use Icso\Accounting\Models\Persediaan\AdjustmentProducts;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Jurnal\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Persediaan\InventoryRepo;
use Icso\Accounting\Repositories\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\SettingEnum;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdjustmentRepo extends ElequentRepository
{
    protected $model;

    public function __construct(Adjustment $model)
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
        })->orderBy('adjustment_date','desc')->with(['warehouse','coa_adjustment','adjustmentproduct','adjustmentproduct.product','adjustmentproduct.coa','adjustmentproduct.unit'])->offset($page)->limit($perpage)->get();
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
        })->orderBy('adjustment_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $userId = $request->user_id;
        $arrData = $this->prepareAdjustmentData($request, $userId);
        DB::beginTransaction();
        try {
            $res = $this->saveAdjustment($arrData, $id, $userId);

            if ($res) {
                $idAdjustment = $this->handleProductsAndFiles($request, $res, $id);
                $this->postingJurnal($idAdjustment);
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

    public function prepareAdjustmentData($request, $userId)
    {
        $refNo = $this->getReferenceNumber($request);
        $adjustmentDate = $this->getAdjustmentDate($request);
        $total = !empty($request->total) ? Utility::remove_commas($request->total) : 0;

        return [
            'ref_no' => $refNo,
            'adjustment_date' => $adjustmentDate,
            'note' => $request->note ?? '',
            'adjustment_type' => $request->adjustment_type ?? 'qty',
            'total' => $total,
            'warehouse_id' => $request->warehouse_id ?? '0',
            'document' => $request->document ?? '',
            'coa_adjustment_id' => $request->coa_adjustment_id ?? '0',
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function getReferenceNumber($request)
    {
        return empty($request->ref_no)
            ? self::generateCodeTransaction(new Adjustment(), KeyNomor::NO_ADJUSTMENT_STOCK, 'ref_no', 'adjustment_date')
            : $request->ref_no;
    }

    private function getAdjustmentDate($request)
    {
        return !empty($request->adjustment_date)
            ? Utility::changeDateFormat($request->adjustment_date)
            : date('Y-m-d');
    }

    public function saveAdjustment($arrData, $id, $userId)
    {
        if (empty($id)) {
            $arrData['adjustment_status'] = StatusEnum::SELESAI;
            $arrData['reason'] = "";
            $arrData['created_at'] = date('Y-m-d H:i:s');
            $arrData['created_by'] = $userId;
            return $this->create($arrData);
        } else {
            return $this->update($arrData, $id);
        }
    }

    public function handleProductsAndFiles($request, $res, $id)
    {
        $idAdjustment = !empty($id) ? $id : $res->id;

        if (!empty($id)) {
            $this->deleteAdditional($id);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->processAdjustmentProducts($request, $idAdjustment);
        $this->handleFileUploads($request, $idAdjustment);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        return $idAdjustment;
    }

    public function processAdjustmentProducts($request, $idAdjustment)
    {

        if (!empty($request->adjustmentproduct)) {
            if (is_array($request->adjustmentproduct)) {
                $products = json_decode(json_encode($request->adjustmentproduct));
            } else {
                $products = $request->adjustmentproduct;
            }
            $this->adjusmentProducts($products, $idAdjustment);
        }
    }

    public function adjusmentProducts($products, $idAdjustment)
    {
        if (count($products) > 0) {
            foreach ($products as $item) {
                $arrItem = [
                    'qty_tercatat' => $item->qty_tercatat,
                    'qty_actual' => $item->qty_actual,
                    'qty_selisih' => $item->qty_selisih,
                    'hpp' => !empty($item->hpp) ? Utility::remove_commas($item->hpp) : 0,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'coa_id' => !empty($item->product) ? $item->product->coa_id : 0,
                    'adjustment_id' => $idAdjustment,
                    'subtotal' => 0,
                ];
                AdjustmentProducts::create($arrItem);
            }
        }
    }

    public function handleFileUploads($request, $idAdjustment)
    {
        $fileUpload = new FileUploadService();
        $uploadedFiles = $request->file('files');
        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                if ($resUpload) {
                    AdjustmentMeta::create([
                        'adjustment_id' => $idAdjustment,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload,
                    ]);
                }
            }
        }
    }


    public function deleteAdditional($id)
    {
        AdjustmentProducts::where(array('adjustment_id' => $id))->delete();
        AdjustmentMeta::where(array('adjustment_id' => $id))->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::ADJUSTMENT, $id);
        Inventory::where(array('transaction_code' => TransactionsCode::ADJUSTMENT, 'transaction_id' => $id))->delete();

    }

    public function postingJurnal($idAdjustment)
    {
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $inventoryRepo = new InventoryRepo(new Inventory());
        $find = $this->findOne($idAdjustment,array(),['warehouse','coa_adjustment','adjustmentproduct','adjustmentproduct.product','adjustmentproduct.coa','adjustmentproduct.unit']);
        if(!empty($find))
        {
            $adjustmentDate = $find->adjustment_date;
            $refNo = $find->ref_no;
            if(!empty($find->adjustmentproduct)) {
                $adjustmentProduct = $find->adjustmentproduct;
                $debet = 0;
                $kredit = 0;
                if (count($adjustmentProduct) > 0) {
                    foreach ($adjustmentProduct as $item) {
                        $product = $item->product;
                        $productName = "";
                        if(!empty($product)){
                            if(!empty($product->coa_id)){
                                $coaSediaan = $product->coa_id;

                            }
                            $productName = $product->item_name;
                        }
                        $noteProduct = !empty($productName) ? " dengan nama ".$productName : "";
                        $selisih = $item->qty_selisih;
                        $qty = abs($selisih);
                        $hpp = $item->hpp;
                        $subtotal = $hpp;
                        if ($find->adjustment_type == VarType::ADJUSTMENT_TYPE_QTY) {
                          //  $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $adjustmentDate);
                            $subtotal = $hpp * $qty;
                        }
                        $arrUpdateItem = array(
                            'subtotal' => $subtotal
                        );
                        AdjustmentProducts::where(array('id' => $item->id))->update($arrUpdateItem);
                        if ($find->adjustment_type == VarType::ADJUSTMENT_TYPE_QTY) {
                            if($selisih < 0){
                                $reqInventory = new Request();
                                $reqInventory->coa_id = $coaSediaan;
                                $reqInventory->user_id = $find->created_by;
                                $reqInventory->inventory_date = $adjustmentDate;
                                $reqInventory->transaction_code = TransactionsCode::ADJUSTMENT;
                                $reqInventory->transaction_id = $find->id;
                                $reqInventory->transaction_sub_id = $item->id;
                                $reqInventory->qty_out = $qty;
                                $reqInventory->warehouse_id = $find->warehouse_id;
                                $reqInventory->product_id = $item->product_id;
                                $reqInventory->price = $hpp;
                                $reqInventory->note = $find->note;
                                $reqInventory->unit_id = $item->unit_id;
                                $inventoryRepo->store($reqInventory);
                                $kredit = $kredit + $subtotal;
                                $arrJurnalKredit = array(
                                    'transaction_date' => $adjustmentDate,
                                    'transaction_datetime' => $adjustmentDate." ".date('H:i:s'),
                                    'created_by' => $find->created_by,
                                    'updated_by' => $find->created_by,
                                    'transaction_code' => TransactionsCode::ADJUSTMENT,
                                    'coa_id' => $coaSediaan,
                                    'transaction_id' => $find->id,
                                    'transaction_sub_id' => $item->id,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                    'transaction_no' => $refNo,
                                    'transaction_status' => JurnalStatusEnum::OK,
                                    'debet' => 0,
                                    'kredit' => $subtotal,
                                    'note' => !empty($find->note) ? $find->note : 'Adjustment Barang'.$noteProduct,
                                );
                                $jurnalTransaksiRepo->create($arrJurnalKredit);
                            } else {
                                $reqInventory = new Request();
                                $reqInventory->coa_id = $coaSediaan;
                                $reqInventory->user_id = $find->created_by;
                                $reqInventory->inventory_date = $adjustmentDate;
                                $reqInventory->transaction_code = TransactionsCode::ADJUSTMENT;
                                $reqInventory->transaction_id = $find->id;
                                $reqInventory->transaction_sub_id = $item->id;
                                $reqInventory->qty_in = $qty;
                                $reqInventory->warehouse_id = $find->warehouse_id;
                                $reqInventory->product_id = $item->product_id;
                                $reqInventory->price = $hpp;
                                $reqInventory->note = $find->note;
                                $reqInventory->unit_id = $item->unit_id;
                                $inventoryRepo->store($reqInventory);
                                $debet = $debet + $subtotal;
                                $arrJurnalDebet = array(
                                    'transaction_date' => $adjustmentDate,
                                    'transaction_datetime' => $adjustmentDate." ".date('H:i:s'),
                                    'created_by' => $find->created_by,
                                    'updated_by' => $find->created_by,
                                    'transaction_code' => TransactionsCode::ADJUSTMENT,
                                    'coa_id' => $coaSediaan,
                                    'transaction_id' => $find->id,
                                    'transaction_sub_id' => $item->id,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                    'transaction_no' => $refNo,
                                    'transaction_status' => JurnalStatusEnum::OK,
                                    'debet' => $subtotal,
                                    'kredit' => 0,
                                    'note' => !empty($find->note) ? $find->note : 'Adjustment Barang'.$noteProduct,
                                );
                                $jurnalTransaksiRepo->create($arrJurnalDebet);
                            }
                        } else {
                            $hppBefore = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $adjustmentDate);
                            $selisihValue = $hpp - $hppBefore;
                            $qtyIn = 0;
                            $qtyOut = 0;
                            $jenisVal = '';
                            if($selisih > 0){
                                $qtyIn = $qty;
                                $jenisVal = 'masuk';
                            } else {
                                $qtyOut = $qty;
                                $jenisVal = 'keluar';
                            }
                            if($selisihValue < 0){
                                if($qtyIn == 0 && $qtyOut == 0){
                                    $jenisVal = 'keluar';
                                }
                                if($jenisVal == 'masuk'){
                                    $selisihValue = -1 * $selisihValue;
                                } else {
                                    $selisihValue = abs($selisihValue);
                                }
                                $reqInventory = new Request();
                                $reqInventory->coa_id = $coaSediaan;
                                $reqInventory->user_id = $find->created_by;
                                $reqInventory->inventory_date = $adjustmentDate;
                                $reqInventory->transaction_code = TransactionsCode::ADJUSTMENT;
                                $reqInventory->transaction_id = $find->id;
                                $reqInventory->transaction_sub_id = $item->id;
                                $reqInventory->qty_in = $qtyIn;
                                $reqInventory->qty_out = $qtyOut;
                                $reqInventory->warehouse_id = $find->warehouse_id;
                                $reqInventory->product_id = $item->product_id;
                                $reqInventory->price = $selisihValue;
                                $reqInventory->note = $find->note;
                                $reqInventory->unit_id = $item->unit_id;
                                $reqInventory->adjustment_type = $find->adjustment_type;
                                $reqInventory->jenis = $jenisVal;
                                $inventoryRepo->store($reqInventory);
                                $kredit = $kredit + $selisihValue;
                                $arrJurnalKredit = array(
                                    'transaction_date' => $adjustmentDate,
                                    'transaction_datetime' => $adjustmentDate." ".date('H:i:s'),
                                    'created_by' => $find->created_by,
                                    'updated_by' => $find->created_by,
                                    'transaction_code' => TransactionsCode::ADJUSTMENT,
                                    'coa_id' => $coaSediaan,
                                    'transaction_id' => $find->id,
                                    'transaction_sub_id' => $item->id,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                    'transaction_no' => $refNo,
                                    'transaction_status' => JurnalStatusEnum::OK,
                                    'debet' => 0,
                                    'kredit' => $selisihValue,
                                    'note' => !empty($find->note) ? $find->note : 'Adjustment Barang'.$noteProduct,
                                );
                                $jurnalTransaksiRepo->create($arrJurnalKredit);
                            } else{
                                if($qtyIn == 0 && $qtyOut == 0){
                                    $jenisVal = 'masuk';
                                }
                                if($jenisVal == 'keluar'){
                                    $selisihValue = -1 * $selisihValue;
                                }
                                $reqInventory = new Request();
                                $reqInventory->coa_id = $coaSediaan;
                                $reqInventory->user_id = $find->created_by;
                                $reqInventory->inventory_date = $adjustmentDate;
                                $reqInventory->transaction_code = TransactionsCode::ADJUSTMENT;
                                $reqInventory->transaction_id = $find->id;
                                $reqInventory->transaction_sub_id = $item->id;
                                $reqInventory->qty_in = $qtyIn;
                                $reqInventory->qty_out = $qtyOut;
                                $reqInventory->warehouse_id = $find->warehouse_id;
                                $reqInventory->product_id = $item->product_id;
                                $reqInventory->price = $selisihValue;
                                $reqInventory->note = $find->note;
                                $reqInventory->unit_id = $item->unit_id;
                                $reqInventory->adjustment_type = $find->adjustment_type;
                                $reqInventory->jenis = $jenisVal;
                                $inventoryRepo->store($reqInventory);
                                $debet = $debet + $selisihValue;
                                $arrJurnalDebet = array(
                                    'transaction_date' => $adjustmentDate,
                                    'transaction_datetime' => $adjustmentDate." ".date('H:i:s'),
                                    'created_by' => $find->created_by,
                                    'updated_by' => $find->created_by,
                                    'transaction_code' => TransactionsCode::ADJUSTMENT,
                                    'coa_id' => $coaSediaan,
                                    'transaction_id' => $find->id,
                                    'transaction_sub_id' => $item->id,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                    'transaction_no' => $refNo,
                                    'transaction_status' => JurnalStatusEnum::OK,
                                    'debet' => $selisihValue,
                                    'kredit' => 0,
                                    'note' => !empty($find->note) ? $find->note : 'Adjustment Barang'.$noteProduct,
                                );
                                $jurnalTransaksiRepo->create($arrJurnalDebet);
                            }
                        }
                    }
                    if(abs($debet) > abs($kredit)){
                        $saldo = $debet - $kredit;
                        $arrJurnalKredit = array(
                            'transaction_date' => $adjustmentDate,
                            'transaction_datetime' => $adjustmentDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::ADJUSTMENT,
                            'coa_id' => $find->coa_adjustment_id,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => 0, // Fixed: Use 0 instead of $item->id
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $refNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => 0,
                            'kredit' => $saldo,
                            'note' => !empty($find->note) ? $find->note : 'Adjustment Barang',
                        );
                        $jurnalTransaksiRepo->create($arrJurnalKredit);
                    } else {
                        $saldo = $kredit - $debet;
                        $arrJurnalDebet = array(
                            'transaction_date' => $adjustmentDate,
                            'transaction_datetime' => $adjustmentDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::ADJUSTMENT,
                            'coa_id' => $find->coa_adjustment_id,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => 0, // Fixed: Use 0 instead of $item->id
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $refNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $saldo,
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Adjustment Barang',
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);
                    }
                }

            }
        }
    }

}
