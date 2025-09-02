<?php

namespace Icso\Accounting\Imports;


use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequest;
use Icso\Accounting\Repositories\Pembelian\Request\RequestRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use stdClass;

class PurchaseRequestImport implements ToCollection
{
    protected $userId;
    private $errors = [];
    protected $reqRepo;
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->reqRepo = new RequestRepo(new PurchaseRequest());
    }

    /**
    * @param Collection $collection
    */
    public function collection(Collection $rows)
    {
        $oldNo = '0';
        $idPermintaan = '0';
        $statIns = false;
        foreach ($rows as $index => $row) {
            // Skip the header row
            if ($index === 0) {
                continue;
            }

            $this->totalRows++;

            $noPermintaan = $row[0];
            $tanggalPermintaan = $row[1];
            $tanggalButuh = $row[2];
            $permintaanDari = $row[3];
            $note = $row[4];
            $itemCode = $row[5];
            $qty = $row[6];
            $noteItem = $row[7];

            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }

            if ($index > 1 && $statIns) {
                $oldNo = $rows[$index - 1][0];
                $statIns = false;
            }
            if(!empty($tanggalPermintaan)){
                $tanggalPermintaan = Helpers::formatDateExcel($tanggalPermintaan);
            }
            if(!empty($tanggalButuh)){
                $tanggalButuh = Helpers::formatDateExcel($tanggalButuh);
            }

            if ($oldNo === '0') {
                $idPermintaan = $this->insertPurchaseRequestEntry($noPermintaan,$tanggalPermintaan,$tanggalButuh,$permintaanDari, $note, $itemCode, $qty, $noteItem);
                if($idPermintaan){
                    $this->successCount++;
                }
                $statIns = true;
            } elseif ($oldNo !== $noPermintaan) {
                $idPermintaan = $this->insertPurchaseRequestEntry($noPermintaan,$tanggalPermintaan,$tanggalButuh,$permintaanDari, $note, $itemCode, $qty, $noteItem);
                if($idPermintaan){
                    $this->successCount++;
                }
                $statIns = true;
            } else {
                $this->insertPurchaseRequestProduct($idPermintaan, $itemCode, $qty, $noteItem);
            }
        }
    }

    private function insertPurchaseRequestEntry($noPermintaan, $tanggalPermintaan, $tanggalButuh, $permintaanDari, $note, $itemCode, $qty, $noteItem)
    {

        $arrData = $this->reqRepo->prepareDataArray($noPermintaan,$tanggalPermintaan,$note,$permintaanDari,$tanggalButuh,"biasa",$this->userId);
        $res = $this->reqRepo->saveRequest($arrData, "", $this->userId);
        if($res){
            $this->insertPurchaseRequestProduct($res->id,$itemCode,$qty,$noteItem);
            return $res->id;
        }
        return 0;
    }

    private function insertPurchaseRequestProduct($idPermintaan, $itemCode, $qty, $noteItem)
    {
        $findBarang = Product::where('item_code', $itemCode)->first();
        if(!empty($findBarang)){
            $product1 = new stdClass();
            $product1->qty = $qty;
            $product1->product_id = $findBarang->id;
            $product1->unit_id = $findBarang->unit_id;
            $product1->note = $noteItem;
            $products = [$product1];
            $this->reqRepo->saveProducts($products, $idPermintaan);
        }
    }

    private function hasValidationErrors($index, $row)
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": No permintaan Kosong.";
            return true;
        }

        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal permintaan Kosong.";
            return true;
        }

        if (empty($row[3])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Permintaan dari Kosong.";
            return true;
        }

        if (empty($row[5])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode barang Kosong.";
            return true;
        } else {
            $findBarang = Product::where('item_code', $row[5])->first();
            if(empty($findBarang)){
                $this->errors[] = "Baris " . ($index + 1) . ": Kode barang tidak ditemukan.";
                return true;
            }
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

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getTotalRows()
    {
        return $this->totalRows;
    }
}
