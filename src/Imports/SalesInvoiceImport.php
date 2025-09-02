<?php

namespace Icso\Accounting\Imports;

use App\Repositories\Master\WarehouseRepo;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Repositories\Master\TaxRepo;
use Icso\Accounting\Repositories\Master\Vendor\VendorRepo;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use stdClass;

class SalesInvoiceImport implements ToCollection
{

    protected $userId;
    protected $orderType;
    private $errors = [];
    private $success = [];
    protected $invoiceRepo;
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId, $orderType){
        $this->userId = $userId;
        $this->orderType = $orderType;
        $this->invoiceRepo = new InvoiceRepo(new SalesInvoicing());
    }

    /**
    * @param Collection $collection
    */
    public function collection(Collection $rows)
    {
        $arrListIdInvoice = array();
        $idInvoice = '0';
        $oldNo = '0';
        $statIns = false;
        foreach ($rows as $index => $row) {
            // Skip the header row
            if ($index === 0) {
                continue;
            }
            $this->totalRows++;

            $noInvoice = $row[0];
            $tanggalInvoice = $row[1];
            $warehouseId = 0;
            if($this->orderType == ProductType::SERVICE){
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
            } else {
                $kodeGudang = $row[2];
                $kodeCustomer = $row[3];
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
                $warehouseId = WarehouseRepo::getWarehouseId($kodeGudang);
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

            if(!empty($tanggalInvoice)){
                $tanggalInvoice = Helpers::formatDateExcel($tanggalInvoice);
            }

            $vendorId = VendorRepo::getVendorId($kodeCustomer);


            if ($oldNo == '0') {
                $idInvoice = $this->insertInvoiceEntry($noInvoice,$tanggalInvoice,$note,$warehouseId,$vendorId,$diskon,$diskonType,$tipePpn,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$persenPpn);
                if($idInvoice){
                    $this->successCount++;
                }
                $arrListIdInvoice[] = $idInvoice;
                $statIns = true;

            } elseif ($oldNo != $noInvoice) {
                $idInvoice = $this->insertInvoiceEntry($noInvoice,$tanggalInvoice,$note,$warehouseId,$vendorId,$diskon,$diskonType,$tipePpn,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$persenPpn);
                if($idInvoice){
                    $this->successCount++;
                }
                $arrListIdInvoice[] = $idInvoice;
                $statIns = true;


            } else {
                $this->insertInvoiceProduct($itemCode, $qty,$price,$diskonItem,$diskonItemType, $tipePpn, $persenPpn, $idInvoice);
            }
        }
        if(!empty($arrListIdInvoice)){
            foreach ($arrListIdInvoice as $invId) {
                $this->updateDataInvoice($invId);
            }
        }
    }

    public function insertInvoiceEntry($invoiceNo, $invoiceDate, $note, $warehouseId, $vendorId, $discount,$discountType,$taxType,$itemCode,$qty,$price,$diskonItem,$diskonItemType,$taxPercentage)
    {
        $request = new Request();
        $request->invoice_no = $invoiceNo;
        $request->invoice_date = $invoiceDate;
        $request->note = $note;
        $request->tax_type = $taxType;
        $request->discount_type = $discountType;
        $request->vendor_id = $vendorId;
        $request->warehouse_id = $warehouseId;
        $request->subtotal = 0;
        $request->discount = $discount;
        $request->grandtotal = 0;
        $request->invoice_type = $this->orderType;
        $request->input_type = InputType::SALES;
        $request->order_id = 0;
        $request->due_date = "";
        $arrData = $this->invoiceRepo->prepareInvoiceData($request, $invoiceNo, $invoiceDate, $note, "", $this->userId,$vendorId);
        $arrData = $this->invoiceRepo->prepareCreateOrUpdateData($request, $arrData, "");
        $res = $this->invoiceRepo->createOrUpdateInvoice($arrData,'');
        if($res){
            $this->insertInvoiceProduct($itemCode,$qty,$price,$diskonItem,$diskonItemType,$taxType,$taxPercentage,$res->id);
            $this->success[] = "No Invoice ".$invoiceNo." berhasil import";
            return $res->id;
        } else{
            $this->errors[] = "No Invoice ".$invoiceNo." gagal import";
            return 0;
        }

    }

    public function insertInvoiceProduct($itemCode, $qty,$price,$diskonItem,$diskonItemType, $taxType, $taxPercentage, $invoiceId)
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
        $products = [$product1];
        $this->invoiceRepo->handleOrderProducts($products,$invoiceId,$taxType);

    }

    private function hasValidationErrors($index, $row)
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": No Order Penjualan Kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal Order Penjualan Kosong.";
            return true;
        }

        if (empty($row[2])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode customer Kosong.";
            return true;
        }
        if (!Vendor::where('vendor_code', $row[2])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode customer tidak ditemukan.";
            return true;
        }
        if (empty($row[3]) || !Warehouse::where('warehouse_code', $row[2])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode gudang tidak ditemukan.";
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
        if (empty($row[2])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode supplier dari Kosong.";
            return true;
        }
        if (!Vendor::where('vendor_code', $row[2])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode supplier tidak ditemukan.";
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
        if (empty($row[7])) {
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
        if (!empty($row[11]) && !in_array($row[11], ['percent', 'fix'])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tipe diskon item tidak valid.";
            return true;
        }
        if (!empty($row[12]) && !Tax::where('tax_percentage', $row[12])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Pajak tidak ditemukan.";
            return true;
        }
        return false;
    }

    private function updateDataInvoice($idInvoice)
    {
        $find = $this->invoiceRepo->findOne($idInvoice,array(),['orderproduct']);
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
            $find->discount_total = $calc['discount'];
            $find->save();
            $this->invoiceRepo->postingJurnal($idInvoice);
        }

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
