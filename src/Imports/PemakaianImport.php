<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Persediaan\StockUsage;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Icso\Accounting\Repositories\Master\Product\ProductRepo;
use Icso\Accounting\Repositories\Master\WarehouseRepo;
use Icso\Accounting\Repositories\Persediaan\Pemakaian\PemakaianRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use stdClass;

class PemakaianImport implements ToCollection
{

    protected $userId;
    private $errors = [];
    private $success = [];

    private $totalRows = 0;
    private $successCount = 0;
    protected $pemakaianRepo;

    public function __construct($userId)
    {
        $this->pemakaianRepo = new PemakaianRepo(new StockUsage());
        $this->userId = $userId;
    }

    /**
    * @param Collection $rows
    */
    public function collection(Collection $rows)
    {
        $arrListIdPemakaian = array();
        $idPemakaian = '0';
        $oldNo = '0';
        $statIns = false;
        foreach ($rows as $index => $row) {
            // Skip the header row
            if ($index === 0) {
                continue;
            }
            $this->totalRows++;
            $noPemakaian = $row[0];
            $tanggalPemakaian = $row[1];
            $kodeGudang = $row[2];
            $note = $row[3];
            $KodeBarang = $row[4];
            $qty = $row[5];
            $kodeCoaPemakaian = $row[6];
            $noteItem = $row[7];
            $warehouseId = WarehouseRepo::getWarehouseId($kodeGudang);
            $coaId = CoaRepo::getCoaId($kodeCoaPemakaian);
            $productId = ProductRepo::getProductId($KodeBarang);
            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }

            if ($index > 1 && $statIns) {
                $oldNo = $rows[$index - 1][0];
                $statIns = false;
            }
            if(!empty($tanggalPemakaian)){
                $tanggalPemakaian = Helpers::formatDateExcel($tanggalPemakaian);
            }

            if ($oldNo == '0') {
                $idPemakaian = $this->insertPemakaianEntry($noPemakaian,$tanggalPemakaian,$warehouseId,$note,$productId,$qty,$coaId, $noteItem);
                if($idPemakaian){
                    $this->successCount++;
                }
                $arrListIdPemakaian[] = $idPemakaian;
                $statIns = true;
            } elseif ($oldNo != $noPemakaian) {
                $idPemakaian = $this->insertPemakaianEntry($noPemakaian,$tanggalPemakaian,$warehouseId,$note,$productId,$qty,$coaId, $noteItem);
                if($idPemakaian){
                    $this->successCount++;
                }
                $arrListIdPemakaian[] = $idPemakaian;
                $statIns = true;
            } else {
                $this->insertPemakaianProduct($idPemakaian,$productId,$qty,$coaId, $noteItem);
            }
        }
        if(!empty($arrListIdPemakaian)){
            foreach ($arrListIdPemakaian as $pakaiId) {
                $this->pemakaianRepo->postingJurnal($pakaiId);
            }
        }
    }

    public function insertPemakaianEntry($noPemakaian,$tanggalPemakaian,$gudangId,$note,$produkId,$qty,$coaId,$noteItem)
    {
        $request = new Request();
        $request->ref_no = $noPemakaian;
        $request->usage_date = $tanggalPemakaian;
        $request->note = $note;
        $request->warehouse_id = $gudangId;
        $arrData = $this->pemakaianRepo->prepareData($request);
        $res = $this->pemakaianRepo->saveData($arrData,"",$this->userId);
        if($res){
            $this->insertPemakaianProduct($res->id,$produkId,$qty,$coaId,$noteItem);
            return $res->id;
        }
        return 0;
    }

    public function insertPemakaianProduct($idPemakaian,$productId,$qty,$coaId,$noteItem)
    {
        $unitId = 0;
        $findProduct = Product::where('id', $productId)->first();
        if($findProduct){
            $unitId = $findProduct->unit_id;
        }

        $product1 = new stdClass();
        $product1->product = $findProduct;
        $product1->product_id = $productId;
        $product1->qty = $qty;
        $product1->coa_id = $coaId;
        $product1->note = $noteItem;
        $product1->unit_id = $unitId;
        $products = [$product1];
        $this->pemakaianRepo->usageProducts($products,$idPemakaian);
    }

    private function hasValidationErrors($index, $row)
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": No Pemakaian Kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal pemakaian Kosong.";
            return true;
        }
        if (empty($row[2]) || !Warehouse::where('warehouse_code', $row[2])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode gudang tidak ditemukan.";
            return true;
        }

        if (empty($row[4]) || !Product::where('item_code', $row[4])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode barang tidak ditemukan.";
            return true;
        }
        if (empty($row[5])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kuantiti Kosong.";
            return true;
        }

        if (empty($row[6])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode coa pemakaian Kosong.";
            return true;
        }
        if (!Coa::where('coa_code', $row[6])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode coa pemakaian tidak ditemukan.";
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
