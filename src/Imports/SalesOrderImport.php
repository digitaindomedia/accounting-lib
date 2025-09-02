<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
use Icso\Accounting\Repositories\Master\TaxRepo;
use Icso\Accounting\Repositories\Master\Vendor\VendorRepo;
use Icso\Accounting\Repositories\Penjualan\Order\SalesOrderRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\VarType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use stdClass;

class SalesOrderImport implements ToCollection
{

    protected $userId;
    protected $orderType;
    private $errors = [];
    private $successes = [];

    private $totalRows = 0;
    private $successCount = 0;
    protected $orderRepo;

    public function __construct($userId, $orderType)
    {
        $this->userId = $userId;
        $this->orderType = $orderType;
        $this->orderRepo = new SalesOrderRepo(new SalesOrder());
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
            $kodeCustomer = $row[2];
            $note = $row[3];
            $diskon = $row[4];
            $diskonType = $row[5];
            $tipePpn = $row[6];
            $itemCode = $row[7];
            $qty = $row[8];
            $price = $row[9];
            $diskonItem = $row[10];
            $diskonItemType = $row[11];
            $persenPpn = $row[12];
            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }
            if(!empty($tanggalOrder)){
                $tanggalOrder = Helpers::formatDateExcel($tanggalOrder);
            }
            if ($index > 1 && $statIns) {
                $oldNo = $rows[$index - 1][0];
                $statIns = false;
            }
            $vendorId = VendorRepo::getVendorId($kodeCustomer);
            if ($oldNo == '0') {
                $idOrder = $this->insertSalesOrderEntry($noOrder,$tanggalOrder,$note,$vendorId,$diskon,$diskonType,$tipePpn,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$persenPpn);
                if($idOrder){
                    $this->successCount++;
                }
                $arrListIdOrder[] = $idOrder;
                $statIns = true;

            } elseif ($oldNo != $noOrder) {
                $idOrder = $this->insertSalesOrderEntry($noOrder,$tanggalOrder,$note,$vendorId,$diskon,$diskonType,$tipePpn,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$persenPpn);
                if($idOrder){
                    $this->successCount++;
                }
                $arrListIdOrder[] = $idOrder;
                $statIns = true;


            } else {
                $this->insertSalesOrderProduct($idOrder, $itemCode, $qty,$price,$diskonItem,$diskonItemType, $tipePpn, $persenPpn);
            }
        }
        if(!empty($arrListIdOrder)){
            foreach ($arrListIdOrder as $idOrder) {
                $this->updateDataOrder($idOrder);
            }
        }
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
            $res = $find->save();
            if($res){
                $this->successes[] = "No Order ".$find->order_no." berhasil import";
            }
        }
    }

    public function insertSalesOrderEntry($orderNo,$orderDate,$note,$vendorId,$discount,$discountType,$taxType,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$taxPercentage)
    {
        $arrData = $this->orderRepo->preparedDataArray($orderNo,$orderDate,$this->userId,$note,'',$vendorId,0,$discount, 0,$discountType,$taxType,0,'','','');
        $res = $this->orderRepo->handleNewData($arrData, $this->orderType, $this->userId);
        if($res){
            $this->insertSalesOrderProduct($res->id,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$taxType,$taxPercentage);
            return $res->id;
        }
        return 0;
    }

    private function insertSalesOrderProduct($orderId, $itemCode, $qty,$price,$diskonItem,$diskonItemType, $taxType, $taxPercentage)
    {
        $product1 = new stdClass();
        $findBarang = Product::where('item_code', $itemCode)->first();
        if(!empty($findBarang)){
            $product1->product_id = $findBarang->id;
            $product1->unit_id = $findBarang->unit_id;
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
        $this->orderRepo->handleOrderProducts($products, $orderId, $taxType);
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
        if (empty($row[2])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode customer dari Kosong.";
            return true;
        }
        if (!Vendor::where('vendor_code', $row[2])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode customer tidak ditemukan.";
            return true;
        }
        if (!empty($row[5]) && !in_array($row[5], ['percent', 'fix'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe diskon tidak valid.";
            return true;
        }
        if (empty($row[6]) || !in_array($row[6], ['include', 'exclude'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe PPN tidak valid.";
            return true;
        }
        if (empty($row[7]) || !Product::where('item_code', $row[7])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode barang tidak ditemukan.";
            return true;
        }
        if (empty($row[8])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kuantiti Kosong.";
            return true;
        }
        if (empty($row[9])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Harga satuan Kosong.";
            return true;
        }
        if (!empty($row[10]) && !in_array($row[10], ['percent', 'fix'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe diskon item tidak valid.";
            return true;
        }
        if (!empty($row[12]) && !Tax::where('tax_percentage', $row[12])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Pajak tidak ditemukan.";
            return true;
        }
        return false;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccess()
    {
        return $this->successes;
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
