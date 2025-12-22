<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Icso\Accounting\Repositories\Master\Product\ProductRepo;
use Icso\Accounting\Repositories\Master\WarehouseRepo;
use Icso\Accounting\Repositories\Persediaan\Adjustment\AdjustmentRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use stdClass;

class AdjustmentImport implements ToCollection
{
    protected $userId;
    private $errors = [];
    private $success = [];
    protected $adjustmentRepo;
    protected $inventoryRepo;
    private $totalRows = 0;
    private $successCount = 0;
    public function __construct($userId){
        $this->userId = $userId;
        $this->adjustmentRepo = new AdjustmentRepo(new Adjustment());
        $this->inventoryRepo = new InventoryRepo(new Inventory());
    }
    /**
    * @param Collection $rows
    */
    public function collection(Collection $rows)
    {
        $arrListIdAdjustment = array();
        $idAdjustment = '0';
        $oldNo = '0';
        $statIns = false;
        foreach ($rows as $index => $row) {
            // Skip the header row
            if ($index === 0) {
                continue;
            }
            $this->totalRows++;
            $noPenyesuaian = $row[0];
            $tanggalPenyesuaian = $row[1];
            $kodeCoa = $row[2];
            $kodeGudang = $row[3];
            $note = $row[4];
            $KodeBarang = $row[5];
            $qty = $row[6];
            $hpp = $row[7];
            $warehouseId = WarehouseRepo::getWarehouseId($kodeGudang);
            $coaId = CoaRepo::getCoaId($kodeCoa);
            $productId = ProductRepo::getProductId($KodeBarang);
            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }
            if(!empty($tanggalPenyesuaian)){
                $tanggalPenyesuaian = Helpers::formatDateExcel($tanggalPenyesuaian);
            }

            if ($index > 1 && $statIns) {
                $oldNo = $rows[$index - 1][0];
                $statIns = false;
            }

            if ($oldNo == '0') {
                $idAdjustment = $this->insertAdjustmentEntry($noPenyesuaian,$tanggalPenyesuaian,$warehouseId,$coaId,$note,$productId,$qty,$hpp);
                if($idAdjustment){
                    $this->successCount++;
                }
                $arrListIdAdjustment[] = $idAdjustment;
                $statIns = true;
            } elseif ($oldNo != $noPenyesuaian) {
                $idAdjustment = $this->insertAdjustmentEntry($noPenyesuaian,$tanggalPenyesuaian,$warehouseId,$coaId,$note,$productId,$qty,$hpp);
                if($idAdjustment){
                    $this->successCount++;
                }
                $arrListIdAdjustment[] = $idAdjustment;
                $statIns = true;
            } else {
                $this->insertAdjustmentProduct($idAdjustment,$tanggalPenyesuaian,$warehouseId,$productId,$qty,$hpp);
            }
        }
        if(!empty($arrListIdAdjustment)){
            foreach ($arrListIdAdjustment as $adjId) {
                $this->adjustmentRepo->postingJurnal($adjId);
            }
        }
    }

    public function insertAdjustmentEntry($noPenyesuaian,$tanggalPenyesuaian,$gudangId,$coaId,$note,$produkId,$qty,$hpp)
    {
        $request = new Request();
        $request->ref_no = $noPenyesuaian;
        $request->adjustment_date = $tanggalPenyesuaian;
        $request->warehouse_id = $gudangId;
        $request->coa_adjustment_id = $coaId;
        $request->note = $note;
        $arrData = $this->adjustmentRepo->prepareAdjustmentData($request,$this->userId);
        $res = $this->adjustmentRepo->saveAdjustment($arrData,"",$this->userId);
        if($res){
            $this->insertAdjustmentProduct($res->id,$tanggalPenyesuaian,$gudangId,$produkId,$qty,$hpp);
            $this->success[] = "No penyesuian ".$noPenyesuaian." berhasil import";
            return $res->id;
        }
        return 0;
    }

    public function insertAdjustmentProduct($idPenyesuaian,$tanggalPenyesuaian,$warehouseId, $productId,$qty,$hpp)
    {
        $unitId = 0;
        $findProduct = Product::where('id', $productId)->first();
        if($findProduct){
            $unitId = $findProduct->unit_id;
        }
        $stock = $this->inventoryRepo->getStokByDate($productId,$warehouseId,$unitId,$tanggalPenyesuaian);
        if(empty($hpp)){
            $hpp = $this->inventoryRepo->movingAverageByDate($productId,$unitId,$tanggalPenyesuaian);
        }
        $selisih = $qty - $stock;
        $product1 = new stdClass();
        $product1->product = $findProduct;
        $product1->product_id = $productId;
        $product1->qty_tercatat = $stock;
        $product1->qty_actual = $qty;
        $product1->qty_selisih = $selisih;
        $product1->hpp = $hpp;
        $product1->unit_id = $unitId;
        $products = [$product1];
        $this->adjustmentRepo->adjusmentProducts($products,$idPenyesuaian);
    }


    private function hasValidationErrors($index, $row)
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": No Penyesuaian Kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal penyesuaian Kosong.";
            return true;
        }

        if (empty($row[2])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode coa penyesuaian Kosong.";
            return true;
        }
        if (!Coa::where('coa_code', $row[2])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode coa tidak ditemukan.";
            return true;
        }
        if (empty($row[3]) || !Warehouse::where('warehouse_code', $row[3])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode gudang tidak ditemukan.";
            return true;
        }

        if (empty($row[5]) || !Product::where('item_code', $row[5])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode barang tidak ditemukan.";
            return true;
        }
        if (empty($row[6])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kuantiti Kosong.";
            return true;
        }

        return false;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccess(){
        return $this->success;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getTotalRows()
    {
        return $this->totalRows;
    }
}
