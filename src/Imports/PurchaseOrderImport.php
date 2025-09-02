<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrder;
use Icso\Accounting\Repositories\Master\TaxRepo;
use Icso\Accounting\Repositories\Master\Vendor\VendorRepo;
use Icso\Accounting\Repositories\Pembelian\Order\OrderRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\VarType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use stdClass;

class PurchaseOrderImport implements ToCollection
{
    protected $userId;
    protected $orderType;
    private $errors = [];
    protected $orderRepo;
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId, $orderType)
    {
        $this->userId = $userId;
        $this->orderType = $orderType;
        $this->orderRepo = new OrderRepo(new PurchaseOrder());
    }

    public function collection(Collection $rows)
    {
        $arrListIdOrder = array();
        $idOrder = '0';
        $oldNo = '0';
        $statIns = false;
        foreach ($rows as $index => $row) {
            // Skip the header row
            if ($index === 0) {
                continue;
            }
            $this->totalRows++;
            $noOrder = $row[0];
            $tanggalOrder = $row[1];
            $kodeAkunBiaya = 0;
            if($this->orderType == ProductType::SERVICE){
                $tanggalKirim = date("Y-m-d");
                $kodeAkunBiaya = $row[2];
                $kodeSupplier = $row[3];
                $note = $row[4];
                $diskon = $row[5];
                $diskonType = $row[6];
                $tipePpn = $row[7];
                $itemCode = $row[8];
                $qty = $row[9];
                $price = $row[10];
                $diskonItem = $row[11];
                $diskonItemType = $row[12];
                $persenPpn = $row[13];
            } else {
                $tanggalKirim = $row[2];
                $kodeSupplier = $row[3];
                $note = $row[4];
                $diskon = $row[5];
                $diskonType = $row[6];
                $tipePpn = $row[7];
                $itemCode = $row[8];
                $qty = $row[9];
                $price = $row[10];
                $diskonItem = $row[11];
                $diskonItemType = $row[12];
                $persenPpn = $row[13];
            }

            if($this->orderType == ProductType::ITEM){
                if ($this->hasValidationErrors($index, $row)) {
                    continue;
                }
            } else {
                if ($this->hasValidationJasaErrors($index, $row)) {
                    continue;
                }
            }


            if ($index > 1 && $statIns) {
                $oldNo = $rows[$index - 1][0];
                $statIns = false;
            }
            if($tanggalOrder){
                $tanggalOrder = Helpers::formatDateExcel($tanggalOrder);
            }

            if(!empty($tanggalKirim)){
                $tanggalKirim = Helpers::formatDateExcel($tanggalKirim);
            }

            $vendorId = VendorRepo::getVendorId($kodeSupplier);

            if ($oldNo == '0') {
                $idOrder = $this->insertPurchaseOrderEntry($noOrder,$tanggalOrder,$note,$tanggalKirim,$vendorId,$diskon,$diskonType,$tipePpn,$this->userId,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$persenPpn, $kodeAkunBiaya);
                if($idOrder){
                    $this->successCount++;
                }
                $arrListIdOrder[] = $idOrder;
                $statIns = true;

            } elseif ($oldNo != $noOrder) {
                $idOrder = $this->insertPurchaseOrderEntry($noOrder,$tanggalOrder,$note,$tanggalKirim,$vendorId,$diskon,$diskonType,$tipePpn,$this->userId,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$persenPpn, $kodeAkunBiaya);
                if($idOrder){
                    $this->successCount++;
                }
                $arrListIdOrder[] = $idOrder;
                $statIns = true;


            } else {
                $this->insertPurchaseOrderProduct($idOrder, $itemCode, $qty,$price,$diskonItem,$diskonItemType, $tipePpn, $persenPpn);
            }
        }
        if(!empty($arrListIdOrder)){
            foreach ($arrListIdOrder as $idOrder) {
                $this->updateDataOrder($idOrder);
            }
        }
    }

    public function insertPurchaseOrderEntry($orderNo,$orderDate,$note,$dateSend,$vendorId,$discount,$discountType,$taxType,$userId,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$taxPercentage, $kodeAkunBiaya=0)
    {
        $akunBiayaId = 0;
        if(!empty($kodeAkunBiaya)){
            $findCoa = Coa::where('coa_code', $kodeAkunBiaya)->first();
            if(!empty($findCoa)){
                $akunBiayaId = $findCoa->id;
            }
        }
        $arrData = $this->orderRepo->prepareDataArray($orderNo,$orderDate,$note,$dateSend,0,$akunBiayaId,$vendorId,0,$discount,$discountType,0,0,$taxType,0,0,$this->orderType,$userId);
        $res = $this->orderRepo->handleNewOrder($arrData,$userId);
        if($res){
            $this->insertPurchaseOrderProduct($res->id, $itemCode, $qty, $price, $diskonItem, $diskonItemType, $taxType, $taxPercentage);
            return $res->id;
        }
        return 0;
    }

    private function insertPurchaseOrderProduct($orderId, $itemCode, $qty,$price,$diskonItem,$diskonItemType, $taxType, $taxPercentage)
    {
        $product1 = new stdClass();
        if($this->orderType == ProductType::ITEM){
            $findBarang = Product::where('item_code', $itemCode)->first();
            if(!empty($findBarang)){
                $product1->product_id = $findBarang->id;
                $product1->service_name = "";
                $product1->unit_id = $findBarang->unit_id;
            }
        } else {
            $product1->product_id = 0;
            $product1->service_name = $itemCode;
            $product1->unit_id = 0;
        }
        $taxId = 0;
        if(!empty($taxPercentage)){
            $taxId = TaxRepo::getTaxId($taxPercentage);
        }

        $subtotal = Helpers::hitungSubtotal($qty,$price,$diskonItem,$diskonItemType);
        $product1->qty = $qty;

        $product1->tax_id = $taxId;
        $product1->tax_percentage = $taxPercentage;
        $product1->price = $price;
        $product1->tax_type = $taxType;
        $product1->subtotal = $subtotal;
        $product1->discount = !empty($diskonItem) ? $diskonItem : 0;

        $product1->discount_type = !empty($diskonItemType) ? $diskonItemType : "fix";
        $product1->request_product_id = 0;
        $products = [$product1];
        $this->orderRepo->processProducts($products, $orderId);
    }

    private function updateDataOrder($idOrder)
    {
        $find = $this->orderRepo->findOne($idOrder, array(), ['orderproduct']);
        if(!empty($find)){
            $orderProducts = $find->orderproduct;
            $subtotal = 0;
            $total = 0;
            if(!empty($orderProducts)){
                foreach($orderProducts as $orderProduct){
                    $taxData = Helpers::hitungTaxDpp($orderProduct->subtotal, $orderProduct->tax_id, $orderProduct->tax_type, $orderProduct->tax_percentage);
                    $subtotal = $subtotal + $orderProduct->subtotal;
                    if(!empty($orderProduct->tax_id)){
                        $total += $orderProduct->subtotal + ($taxData[TypeEnum::TAX_SIGN] === VarType::TAX_SIGN_PEMOTONG ? -$taxData['ppn'] : $taxData['ppn']);
                    } else {
                        $total += $orderProduct->subtotal;
                    }

                }
            }
            $find->subtotal = $subtotal;
            $calc = Helpers::hitungGrandTotal($total, $find->discount, $find->discount_type);
            $find->grandtotal = $calc['grandtotal'];
            $find->total_discount = $calc['discount'];
            $find->save();
        }
    }

    private function hasValidationErrors($index, $row)
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": No Order Pembelian Kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal Order Pembelian Kosong.";
            return true;
        }
        if (empty($row[3])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode supplier dari Kosong.";
            return true;
        }
        if (!Vendor::where('vendor_code', $row[3])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode supplier tidak ditemukan.";
            return true;
        }
        if (!empty($row[6]) && !in_array($row[6], ['percent', 'fix'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe diskon tidak valid.";
            return true;
        }
        if (empty($row[7]) || !in_array($row[7], ['include', 'exclude'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe PPN tidak valid.";
            return true;
        }
        if (empty($row[8]) || !Product::where('item_code', $row[8])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode barang tidak ditemukan.";
            return true;
        }
        if (empty($row[9])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kuantiti Kosong.";
            return true;
        }
        if (empty($row[10])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Harga satuan Kosong.";
            return true;
        }
        if (!empty($row[12]) && !in_array($row[12], ['percent', 'fix'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe diskon item tidak valid.";
            return true;
        }
        if (!empty($row[13]) && !Tax::where('tax_percentage', $row[13])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Pajak tidak ditemukan.";
            return true;
        }
        return false;
    }

    private function hasValidationJasaErrors($index, $row)
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": No Order Pembelian Kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal Order Pembelian Kosong.";
            return true;
        }
        if (!Coa::where('coa_code', $row[2])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode akun biaya tidak ditemukan.";
            return true;
        }
        if (empty($row[3])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode supplier dari Kosong.";
            return true;
        }
        if (!Vendor::where('vendor_code', $row[3])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode supplier tidak ditemukan.";
            return true;
        }
        if (!empty($row[6]) && !in_array($row[6], ['percent', 'fix'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe diskon tidak valid.";
            return true;
        }
        if (empty($row[7]) || !in_array($row[7], ['include', 'exclude'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe PPN tidak valid.";
            return true;
        }
        if (empty($row[8])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode barang tidak ditemukan.";
            return true;
        }
        if (empty($row[9])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kuantiti Kosong.";
            return true;
        }
        if (empty($row[10])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Harga satuan Kosong.";
            return true;
        }
        if (!empty($row[12]) && !in_array($row[12], ['percent', 'fix'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe diskon item tidak valid.";
            return true;
        }
        if (!empty($row[13]) && !Tax::where('tax_percentage', $row[13])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Pajak tidak ditemukan.";
            return true;
        }
        return false;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getTotalRows()
    {
        return $this->totalRows;
    }


    public function getErrors()
    {
        return $this->errors;
    }
}
