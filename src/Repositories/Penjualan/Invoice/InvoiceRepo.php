<?php
namespace Icso\Accounting\Repositories\Penjualan\Invoice;


use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicingDp;
use Icso\Accounting\Enums\InvoiceStatusEnum;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicingDelivery;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicingMeta;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPayment;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentInvoice;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo;
use Icso\Accounting\Repositories\Penjualan\Downpayment\DpRepo;
use Icso\Accounting\Repositories\Penjualan\Order\SalesOrderRepo;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentInvoiceRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceRepo extends ElequentRepository
{

    protected $model;

    public function __construct(SalesInvoicing $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'],$item['value']['value']);
                    }
                    else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%');
            $query->orWhereHas('vendor', function ($query) use($search) {
               $query->where('vendor_name', 'like', '%' .$search. '%');
                $query->orWhere('vendor_company_name', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('order', function ($query) use($search) {
                $query->where('order_no', 'like', '%' .$search. '%');
            });
        })->orderBy('invoice_date','desc')->with(['vendor','order','invoicedelivery','invoicedelivery.delivery.warehouse','invoicedelivery.delivery.deliveryproduct','invoicedelivery.delivery.deliveryproduct.unit','invoicedelivery.delivery.deliveryproduct.product','invoicedelivery.delivery.deliveryproduct.tax','invoicedelivery.delivery.deliveryproduct.tax.taxgroup','invoicedelivery.delivery.deliveryproduct.tax.taxgroup.tax','warehouse','vendor','orderproduct', 'orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit'])->offset($page)->limit($perpage)->get();
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
                        $que->$metod($item['value']['field'],$item['value']['value']);
                    }
                    else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%');
            $query->orWhereHas('vendor', function ($query) use($search) {
                $query->where('vendor_name', 'like', '%' .$search. '%');
                $query->orWhere('vendor_company_name', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('order', function ($query) use($search) {
                $query->where('order_no', 'like', '%' .$search. '%');
            });
        })->orderBy('invoice_date','desc')->with(['vendor'])->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $invoiceNo = $this->getInvoiceNo($request);
        $invoiceDate = $this->getInvoiceDate($request);
        $note = !empty($request->note) ? $request->note : '';
        $dueDate = $this->getDueDate($request);
        $userId = $request->user_id;
        $vendorId = $this->getVendorId($request);
        $arrData = $this->prepareInvoiceData($request, $invoiceNo, $invoiceDate, $note, $dueDate, $userId, $vendorId);

        if ($this->isSalesOrPOS($request)) {
            return $this->handleSalesOrPOS($request, $arrData);
        } else {
            return $this->handleRegularInvoice($request, $arrData);
        }


    }

    private function handleSalesOrPOS(Request $request, array $arrData)
    {
        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $id = $request->id;
            $arrData = $this->prepareCreateOrUpdateData($request, $arrData, $id);
            $res = $this->createOrUpdateInvoice($arrData, $id);
            if ($res) {
                $idInvoice = $this->processInvoiceData($request, $id, $res);
                DB::commit();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                return $idInvoice;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            Log::error($e->getMessage());
            return false;
        }
    }

    public function prepareCreateOrUpdateData(Request $request, array $arrData, $id)
    {
        $invoiceType = $request->invoice_type;
        $inputType = $request->input_type;
        if (empty($id)) {
            $arrData['created_at'] = date('Y-m-d H:i:s');
            $arrData['created_by'] = $request->user_id;
            $arrData['reason'] = '';
            $arrData['invoice_type'] = $invoiceType;
            $arrData['input_type'] = $inputType;
            $arrData['invoice_status'] = $request->input_type == InputType::POS ? InvoiceStatusEnum::LUNAS : InvoiceStatusEnum::BELUM_LUNAS;
        }
        return $arrData;
    }

    public function createOrUpdateInvoice(array $arrData, $id)
    {
        if (empty($id)) {
            return $this->create($arrData);
        } else {
            return $this->update($arrData, $id);
        }
    }

    private function processInvoiceData(Request $request, $id, $res)
    {
        $idInvoice = $id ?? $res->id;
        if (!empty($id)) {
            $this->deleteAdditional($id);
        }
        $this->processOrderProducts($request, $idInvoice);
        $this->processDp($request, $idInvoice);
        $this->processDelivery($request, $idInvoice);

        if ($request->input_type == InputType::POS) {
            $this->processPOSInvoice($request, $idInvoice);
        } else {
            $this->processRegularInvoice($request, $idInvoice);
        }

        return $idInvoice;
    }

    private function processOrderProducts(Request $request, $idInvoice)
    {
        if (!empty($request->orderproduct)) {
            if (is_array($request->orderproduct)) {
                $products = json_decode(json_encode($request->orderproduct));
            } else {
                $products = $request->orderproduct;
            }
            $taxType = $request->tax_type ?? '';
            $this->handleOrderProducts($products, $idInvoice,$taxType);
        }
    }

    public function handleOrderProducts($orderProducts, $idInvoice, $taxType)
    {
        foreach ($orderProducts as $item) {
            SalesOrderProduct::create($this->prepareOrderProductData($item, $idInvoice, $taxType));
        }
    }

    private function prepareOrderProductData($item, $idInvoice, $taxType)
    {
        return [
            'qty' => $item->qty,
            'qty_left' => $item->qty,
            'product_id' => $item->product_id ?? '0',
            'unit_id' => $item->unit_id ?? '0',
            'tax_id' => $item->tax_id ?? '0',
            'tax_percentage' => $item->tax_percentage ?? '0',
            'price' => Utility::remove_commas($item->price ?? 0),
            'tax_type' => $taxType,
            'discount_type' => $item->discount_type ?? '',
            'discount' => Utility::remove_commas($item->discount ?? 0),
            'subtotal' => Utility::remove_commas($item->subtotal ?? 0),
            'multi_unit' => 0,
            'order_id' => 0,
            'invoice_id' => $idInvoice
        ];
    }

    private function processDp(Request $request, $idInvoice)
    {
        if (!empty($request->dp)) {
            $dps = json_decode(json_encode($request->dp));
            foreach ($dps as $dp) {
                SalesInvoicingDp::create([
                    'invoice_id' => $idInvoice,
                    'dp_id' => $dp->id
                ]);
            }
        }
    }

    private function processDelivery(Request $request, $idInvoice)
    {
        if (!empty($request->delivery)) {
            $deliveries = json_decode(json_encode($request->delivery));
            foreach ($deliveries as $item) {
                SalesInvoicingDelivery::create([
                    'invoice_id' => $idInvoice,
                    'delivery_id' => $item->id
                ]);
                SalesOrderRepo::closeStatusOrderById($item->order_id);
            }
        }
    }

    private function processPOSInvoice(Request $request, $idInvoice)
    {
        SalesInvoicingMeta::create([
            'invoice_id' => $idInvoice,
            'meta_key' => 'payment',
            'meta_value' => Utility::remove_commas($request->total_payment)
        ]);
        $this->insertPayment($request, $idInvoice, $this->getInvoiceNo($request));
        $this->postingJurnalPOS($idInvoice, $request);
    }

    private function processRegularInvoice(Request $request, $idInvoice)
    {
        $this->postingJurnal($idInvoice);
        $this->handleFileUploads($request, $idInvoice);
    }

    private function handleFileUploads(Request $request, $idInvoice)
    {
        $fileUpload = new FileUploadService();
        $uploadedFiles = $request->file('files');
        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                if ($resUpload) {
                    SalesInvoicingMeta::create([
                        'invoice_id' => $idInvoice,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }

    private function handleRegularInvoice(Request $request, array $arrData)
    {
        $id = $request->id;
        $arrData = $this->prepareCreateOrUpdateData($request, $arrData, $id);
        return $this->createOrUpdateInvoice($arrData, $id);
    }


    private function isSalesOrPos($request)
    {
        return in_array($request->input_type, [InputType::SALES, InputType::POS]);
    }

    public function prepareInvoiceData($request, $invoiceNo, $invoiceDate, $note, $dueDate, $userId, $vendorId)
    {
        return [
            'invoice_date' => $invoiceDate,
            'invoice_no' => $invoiceNo,
            'note' => $note,
            'due_date' => $dueDate,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
            'vendor_id' => $vendorId,
            'tax_type' => $request->tax_type ?? '',
            'discount_type' => $request->discount_type ?? '',
            'order_id' => $request->order_id ?? '0',
            'dp_nominal' => $request->dp_nominal ?? '0',
            'subtotal' => Utility::remove_commas($request->subtotal),
            'dpp_total' => Utility::remove_commas($request->dpp_total ?? '0'),
            'discount' => Utility::remove_commas($request->discount ?? '0'),
            'discount_total' => Utility::remove_commas($request->discount_total ?? '0'),
            'tax_total' => Utility::remove_commas($request->tax_total ?? '0'),
            'grandtotal' => Utility::remove_commas($request->grandtotal),
            'coa_id' => $request->coa_id ?? '0',
            'warehouse_id' => $request->warehouse_id ?? '0',
            'jurnal_id' => $request->jurnal_id ?? '0'
        ];
    }

    private function getInvoiceNo($request)
    {
        if (empty($request->invoice_no)) {
            return self::generateCodeTransaction(new SalesInvoicing(), KeyNomor::NO_INVOICE_PENJUALAN, 'invoice_no', 'invoice_date');
        }
        return $request->invoice_no;
    }

    private function getInvoiceDate($request)
    {
        return !empty($request->invoice_date) ? Utility::changeDateFormat($request->invoice_date) : date('Y-m-d');
    }

    private function getDueDate($request)
    {
        return !empty($request->due_date) ? $request->due_date : date('Y-m-d');
    }

    private function getVendorId($request)
    {
        return !empty($request->vendor_id) ? $request->vendor_id : SettingRepo::getDefaultCustomer();
    }


    private function insertPayment($request, $idInvoice, $noInvoice){
        $paymentNo = $request->payment_no;
        if(empty($paymentNo)){
            $paymentNo = self::generateCodeTransaction(new SalesPayment(),KeyNomor::NO_PELUNASAN_PENJUALAN,'payment_no','payment_date');
        }
        $paymentDate = !empty($request->invoice_date) ? Utility::changeDateFormat($request->invoice_date) : date('Y-m-d');
        $arrData = array(
            'payment_date' => $paymentDate,
            'payment_no' => $paymentNo,
            'note' => "Pelunasan penjualan kasir",
            'total' => Utility::remove_commas($request->grandtotal),
            'vendor_id' => SettingRepo::getDefaultCustomer(),
            'payment_method_id' => $request->payment_method,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $request->user_id
        );
        $arrData['created_at'] = date('Y-m-d H:i:s');
        $arrData['created_by'] = $request->user_id;
        $arrData['reason'] = '';
        $arrData['payment_status'] = StatusEnum::SELESAI;
        $res = SalesPayment::create($arrData);
        $arrInvoice = array(
            'invoice_no' => $noInvoice,
            'total_payment' => Utility::remove_commas($request->grandtotal),
            'payment_date' => $paymentDate,
            'total_discount' => 0,
            'coa_id_discount' => "",
            'invoice_id' => $idInvoice,
            'payment_id' => $res->id,
            'jurnal_id' => 0,
            'vendor_id' => SettingRepo::getDefaultCustomer(),
            'retur_id' => 0,
            'total_overpayment' => 0,
            'coa_id_overpayment' => ""
        );
        SalesPaymentInvoice::create($arrInvoice);
    }

    public function deleteAdditional($id)
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::INVOICE_PENJUALAN, $id);
        SalesInvoicingDp::where(array('invoice_id' => $id))->delete();
        SalesInvoicingDelivery::where(array('invoice_id' => $id))->delete();
        SalesOrderProduct::where(array('invoice_id' => $id))->delete();
        SalesInvoicingMeta::where(array('invoice_id' => $id))->delete();
    }

    public function postingJurnalPOS($idInvoice,$request)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $find = $this->findOne($idInvoice,array(),['vendor','orderproduct','orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup']);
        if(!empty($find)) {
            $invDate = $find->invoice_date;
            $invNo = $find->invoice_no;
            $retDataSediaan = $this->postingJurnalSediaan($find, $jurnalTransaksiRepo);
            $paymentMetod = PaymentMethod::where(array('id' => $request->payment_method))->first();
            if(!empty($paymentMetod)){
                $arrJurnalDebet = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                    'coa_id' => $paymentMetod->coa_id,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => $find->grandtotal,
                    'kredit' => 0,
                    'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                );
                $jurnalTransaksiRepo->create($arrJurnalDebet);
            }
        }
    }

    public function postingJurnal($idInvoice): void
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $coaUangMukaPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PENJUALAN);
        $coaPiutangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_USAHA);
        $coaPotonganPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_POTONGAN_PENJUALAN);
        $arrCoaProduct = array();
        $arrTax = array();
        $find = $this->findOne($idInvoice,array(),['vendor','orderproduct','orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup']);
        if(!empty($find)) {
            $invDate = $find->invoice_date;
            $invNo = $find->invoice_no;
            $dppPiutang = 0;
            $totalTax = 0;
            //penjualan langsung
            if(empty($find->order_id)){
                if($find->invoice_type == ProductType::SERVICE)
                {
                    $retDataPendapatan = $this->postingJurnalPendapatan($find,$jurnalTransaksiRepo);
                    $dppPiutang = $retDataPendapatan['dpp'];
                    $arrTax = $retDataPendapatan['tax'];
                    $totalTax = $retDataPendapatan['total_tax'];
                }
                else {
                    $retDataSediaan = $this->postingJurnalSediaan($find, $jurnalTransaksiRepo);
                    $dppPiutang = $retDataSediaan['dpp'];
                    $arrTax = $retDataSediaan['tax'];
                    $totalTax = $retDataSediaan['total_tax'];
                }

            }
            else {
                if($find->invoice_type == ProductType::SERVICE)
                {
                    $retDataPendapatan = $this->postingJurnalPendapatan($find,$jurnalTransaksiRepo);
                    $dppPiutang = $retDataPendapatan['dpp'];
                    $arrTax = $retDataPendapatan['tax'];
                    $totalTax = $retDataPendapatan['total_tax'];
                }
                else {
                    //insert pembalik jurnal sediaan dalam perjalanan
                    $retDataSediaan = $this->postingJurnalBebanPokokPenjualan($find, $jurnalTransaksiRepo);
                    $dppPiutang = $retDataSediaan['dpp'];
                    $arrTax = $retDataSediaan['tax'];
                    $totalTax = $retDataSediaan['total_tax'];
                }
            }
            //insert jurnal potongan
            if(!empty($find->discount_total)) {
                $arrJurnalDebet = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                    'coa_id' => $coaPotonganPenjualan,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => $find->discount_total,
                    'kredit' => 0,
                    'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                );
                $jurnalTransaksiRepo->create($arrJurnalDebet);
                $arrJurnalKredit = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                    'coa_id' => $coaPiutangUsaha,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => 0,
                    'kredit' => $find->discount_total,
                    'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                );
                $jurnalTransaksiRepo->create($arrJurnalKredit);
            }

            //insert jurnal uang muka
            $findUangMuka = SalesInvoicingDp::where(array('invoice_id' => $idInvoice))->with(['downpayment','downpayment.tax', 'downpayment.tax.taxgroup','downpayment.tax.taxgroup.tax'])->get();
            if(!empty($findUangMuka)) {
                foreach ($findUangMuka as $itemUangMuka) {
                    $uangMuka = $itemUangMuka->downpayment;
                    $nominal = $uangMuka->nominal;
                    $dppUangMuka = $nominal;
                    $arrTaxUangMuka = array();
                    if ($uangMuka->tax_id != "0") {
                        $objTax = $uangMuka->tax;
                        if (!empty($objTax)) {
                            if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                                if($objTax->is_dpp_nilai_Lain == 1){
                                    $hitung = Helpers::hitungIncludeTaxDppNilaiLain($objTax->tax_percentage,$nominal);
                                    $ppn = $hitung[TypeEnum::PPN];
                                }
                                else {
                                    $hitung = Helpers::hitungIncludeTax($objTax->tax_percentage,$nominal);
                                    $ppn = $hitung[TypeEnum::PPN];
                                }
                                $posisi = "debet";
                                if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                    $posisi = "kredit";
                                    $dppUangMuka = $dppUangMuka + $ppn;
                                } else {
                                    $dppUangMuka = $dppUangMuka - $ppn;
                                }
                                $arrTaxUangMuka[] = array(
                                    'coa_id' => $objTax->sales_coa_id,
                                    'posisi' => $posisi,
                                    'nominal' => $ppn,
                                    'id_item' => $uangMuka->id
                                );
                            } else {
                                $tagGroups = $objTax->taxgroup;
                                if (!empty($tagGroups)) {
                                    $total = $nominal;
                                    foreach ($tagGroups as $group) {
                                        $findTax = $group->tax;
                                        if (!empty($findTax)) {
                                            if($findTax->is_dpp_nilai_Lain == 1){
                                                $hitung = Helpers::hitungIncludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                                $tax = $hitung[TypeEnum::PPN];
                                            }
                                            else {
                                                $hitung = Helpers::hitungIncludeTax($findTax->tax_percentage,$total);
                                                $tax = $hitung[TypeEnum::PPN];
                                            }

                                            $posisi = "debet";
                                            if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                                $posisi = "kredit";
                                                $dppUangMuka = $dppUangMuka + $tax;
                                            } else {
                                                $dppUangMuka = $dppUangMuka - $tax;
                                            }
                                            $arrTaxUangMuka[] = array(
                                                'coa_id' => $findTax->sales_coa_id,
                                                'posisi' => $posisi,
                                                'nominal' => $tax,
                                                'id_item' => $uangMuka->id
                                            );
                                        }
                                    }
                                }
                            }
                        }

                    }

                    //jurnal uang muka debet
                    if (!empty($dppUangMuka)) {
                        $arrJurnalDebet = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate . " " . date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                            'coa_id' => $coaUangMukaPenjualan,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => 0,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $dppUangMuka,
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);
                    }


                    if (!empty($arrTaxUangMuka)) {
                        if (count($arrTaxUangMuka) > 0) {
                            foreach ($arrTaxUangMuka as $val) {
                                if ($val['posisi'] == 'debet') {
                                    $arrJurnalDebet = array(
                                        'transaction_date' => $invDate,
                                        'transaction_datetime' => $invDate . " " . date('H:i:s'),
                                        'created_by' => $find->created_by,
                                        'updated_by' => $find->created_by,
                                        'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                                        'coa_id' => $val['coa_id'],
                                        'transaction_id' => $find->id,
                                        'transaction_sub_id' => 0,
                                        'created_at' => date("Y-m-d H:i:s"),
                                        'updated_at' => date("Y-m-d H:i:s"),
                                        'transaction_no' => $invNo,
                                        'transaction_status' => JurnalStatusEnum::OK,
                                        'debet' => $val['nominal'],
                                        'kredit' => 0,
                                        'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                                    );
                                    $jurnalTransaksiRepo->create($arrJurnalDebet);
                                } else {
                                    $arrJurnalKredit = array(
                                        'transaction_date' => $invDate,
                                        'transaction_datetime' => $invDate . " " . date('H:i:s'),
                                        'created_by' => $find->created_by,
                                        'updated_by' => $find->created_by,
                                        'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                                        'coa_id' => $val['coa_id'],
                                        'transaction_id' => $find->id,
                                        'transaction_sub_id' => 0,
                                        'created_at' => date("Y-m-d H:i:s"),
                                        'updated_at' => date("Y-m-d H:i:s"),
                                        'transaction_no' => $invNo,
                                        'transaction_status' => JurnalStatusEnum::OK,
                                        'debet' => 0,
                                        'kredit' => $val['nominal'],
                                        'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                                    );
                                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                                }
                            }
                        }

                    }
                    //jurnal uang muka kredit
                    if (!empty($nominal)) {
                        $arrJurnalKredit = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate . " " . date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                            'coa_id' => $coaPiutangUsaha,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => 0,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => 0,
                            'kredit' => $nominal,
                            'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                        );
                        $jurnalTransaksiRepo->create($arrJurnalKredit);
                    }
                    DpRepo::changeStatusUangMuka($uangMuka->id);
                }

            }

            //jurnal piutang usaha
            $totalPiutangUsaha = $dppPiutang + $totalTax;
            $arrJurnalDebet = array(
                'transaction_date' => $invDate,
                'transaction_datetime' => $invDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                'coa_id' => $coaPiutangUsaha,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $invNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $totalPiutangUsaha,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);

            if(count($arrTax) > 0){
                foreach ($arrTax as $val){
                    $namaDetail = "";
                    if(!empty($val['nama_item'])){
                        $namaDetail = ' dengan nama item '.$val['nama_item'];
                    }
                    else {
                        if(!empty($find->vendor)){
                            $namaDetail = ' dengan nama customer '.$find->vendor->vendor_company_name ;
                        }
                    }
                    if($val['posisi'] == 'debet'){

                        $arrJurnalDebet = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                            'coa_id' => $val['coa_id'],
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $val['id_item'],
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $val['nominal'],
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan'.$namaDetail,
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);
                    } else
                    {
                        $arrJurnalKredit = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                            'coa_id' => $val['coa_id'],
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $val['id_item'],
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => 0,
                            'kredit' => $val['nominal'],
                            'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan'.$namaDetail,
                        );
                        $jurnalTransaksiRepo->create($arrJurnalKredit);
                    }
                }
            }


            $arrUpdateCoaPiutangUsaha = array(
                'coa_id' => $coaPiutangUsaha
            );
            $this->update($arrUpdateCoaPiutangUsaha, $find->id);
        }
    }

    public function postingJurnalPendapatan($find,$jurnalTransaksiRepo)
    {
        $invDate = $find->invoice_date;
        $invNo = $find->invoice_no;
        $dppPiutang = 0;
        $totalTax = 0;
        $arrTax = array();
        $salesProduct = null;
        $totalPendapatan = 0;
        if(empty($find->order_id)){
            $salesProduct = $find->orderproduct;

        } else {
            $salesProduct = SalesOrderProduct::where(array('order_id' => $find->order_id))->with(['product','tax'])->get();

        }
        if(count($salesProduct) > 0){
            foreach ($salesProduct as $item){
                $product = $item->product;
                $productName = "";
                if(!empty($product)){
                    if(!empty($product->coa_id)){
                        $coaPendapatan = $product->coa_id;
                    }
                    $productName = $product->item_name;
                }
                $noteProduct = !empty($productName) ? " dengan nama ".$productName : "";
                $objTax = $item->tax;
                $subtotal = $item->subtotal;
                $dpp = 0;
                if(!empty($objTax)) {
                    if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                        $posisi = "kredit";
                        if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                            $pembagi = ($item->tax_percentage + 100) / 100;
                            $dpp = $item->subtotal / $pembagi;
                            $dppPiutang = $dppPiutang + $dpp;
                            $tax = ($item->tax_percentage / 100) * $dpp;
                            $totalTax = $totalTax + $tax;

                        } else {
                            $dpp = $subtotal;
                            $tax = ($item->tax_percentage / 100) * $subtotal;
                            $totalTax = $totalTax + $tax;
                            $dppPiutang = $dppPiutang + $item->subtotal;
                        }

                        if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                            $posisi = "debet";
                        }
                        $arrTax[] = array(
                            'coa_id' => $objTax->sales_coa_id,
                            'posisi' => $posisi,
                            'nominal' => $tax,
                            'nama_item' => !empty($product) ? $product->item_name: "",
                            'id_item' => $item->id
                        );
                    } else {
                        $tagGroups = $objTax->taxgroup;
                        if (!empty($tagGroups)) {
                            $total = $subtotal;
                            foreach ($tagGroups as $group) {
                                $findTax = $group->tax;
                                if (!empty($findTax)) {
                                    if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                                        $pembagi = ($findTax->tax_percentage + 100) / 100;
                                        $subtotal = $total / $pembagi;
                                    }
                                    $dpp = $dpp + $subtotal;
                                    $dppPiutang = $dppPiutang + $subtotal;
                                    $tax = ($findTax->tax_percentage / 100) * $subtotal;
                                    $totalTax = $totalTax + $tax;
                                    $posisi = "kredit";
                                    if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                        $posisi = "debet";
                                    }
                                    $arrTax[] = array(
                                        'coa_id' => $findTax->sales_coa_id,
                                        'posisi' => $posisi,
                                        'nominal' => $tax,
                                        'nama_item' => $product->item_name,
                                        'id_item' => $item->id
                                    );
                                }
                            }
                        }
                    }
                }
                else {
                    $dpp = $item->subtotal;
                    $dppPiutang = $dppPiutang +  $item->subtotal;
                }
                $arrJurnalKredit = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                    'coa_id' => $coaPendapatan,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => $item->id,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => 0,
                    'kredit' => $dpp,
                    'note' => !empty($find->note) ? $find->note : 'Invoice penjualan jasa'.$noteProduct,
                );
                $jurnalTransaksiRepo->create($arrJurnalKredit);
                $totalPendapatan = $totalPendapatan + $dpp;
            }
        }
        return array(
            'dpp' => $dppPiutang,
            'tax' => $arrTax,
            'total_tax' => $totalTax
        );
    }

    public function postingJurnalSediaan($find,$jurnalTransaksiRepo)
    {
        $dppPiutang = 0;
        $totalTax = 0;
        $coaPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_PENJUALAN);
        $inventoryRepo = new InventoryRepo(new Inventory());
        $coaBebanPokokPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_POKOK_PENJUALAN);
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $invProduct = $find->orderproduct;
        $invDate = $find->invoice_date;
        $invNo = $find->invoice_no;
        $totalSediaan = 0;
        $arrTax = array();
        if(count($invProduct) > 0) {
            foreach ($invProduct as $key => $item) {
                $product = $item->product;
                $productName = "";
                if(!empty($product)){
                    if(!empty($product->coa_id)){
                        $coaSediaan = $product->coa_id;
                    }
                    $productName = $product->item_name;
                }
                $noteProduct = !empty($productName) ? " dengan nama ".$productName : "";
                $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $invDate);
                $subtotalHpp = $hpp * $item->qty;
                $reqInventory = new Request();
                $reqInventory->coa_id = $coaSediaan;
                $reqInventory->user_id = $find->created_by;
                $reqInventory->inventory_date = $invDate;
                $reqInventory->transaction_code = TransactionsCode::INVOICE_PENJUALAN;
                $reqInventory->transaction_id = $find->id;
                $reqInventory->transaction_sub_id = $item->id;
                $reqInventory->qty_out = $item->qty;
                $reqInventory->warehouse_id = $find->warehouse_id;
                $reqInventory->product_id = $item->product_id;
                $reqInventory->price = $hpp;
                $reqInventory->note = $find->note;
                $reqInventory->unit_id = $item->unit_id;
                $inventoryRepo->store($reqInventory);
                if(!empty($subtotalHpp)){
                    $arrJurnalKredit = array(
                        'transaction_date' => $invDate,
                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                        'coa_id' => $coaSediaan,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => $item->id,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $invNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => 0,
                        'kredit' => $subtotalHpp,
                        'note' => !empty($find->note) ? $find->note : 'Invoice penjualan Barang'.$noteProduct,
                    );
                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                }

                $totalSediaan = $totalSediaan + $subtotalHpp;
                $objTax = $item->tax;
                $subtotal = $item->subtotal;
                $dpp = 0;
                if(!empty($objTax)) {

                    if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                        $posisi = "kredit";

                        if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                            if($objTax->is_dpp_nilai_Lain == 1){
                                $hitung = Helpers::hitungIncludeTaxDppNilaiLain($item->tax_percentage,$item->subtotal);
                                $tax = $hitung[TypeEnum::PPN];
                                //$dpp = $hitung[TypeEnum::DPP];
                            }
                            else {
                                $hitung = Helpers::hitungIncludeTax($item->tax_percentage,$item->subtotal);
                                $tax = $hitung[TypeEnum::PPN];
                              //  $dpp = $hitung[TypeEnum::DPP];
                            }
                            $dpp = $item->subtotal - $tax;
                            $dppPiutang = $dppPiutang + $dpp;
                            $totalTax = $totalTax + $tax;

                        } else {
                            if($objTax->is_dpp_nilai_Lain == 1){
                                $hitung = Helpers::hitungExcludeTaxDppNilaiLain($item->tax_percentage,$subtotal);
                                $tax = $hitung[TypeEnum::PPN];

                            }
                            else {
                                $hitung = Helpers::hitungExcludeTax($item->tax_percentage,$subtotal);
                                $tax = $hitung[TypeEnum::PPN];
                            }
                            $dpp = $subtotal;
                            $totalTax = $totalTax + $tax;
                            $dppPiutang = $dppPiutang + $item->subtotal;
                        }

                        if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                            $posisi = "debet";
                        }
                        $arrTax[] = array(
                            'coa_id' => $objTax->sales_coa_id,
                            'posisi' => $posisi,
                            'nominal' => $tax,
                            'nama_item' => !empty($product) ? $product->item_name: "",
                            'id_item' => $item->id
                        );
                    } else {
                        $tagGroups = $objTax->taxgroup;
                        if (!empty($tagGroups)) {
                            $total = $subtotal;
                            foreach ($tagGroups as $group) {
                                $findTax = $group->tax;
                                if (!empty($findTax)) {
                                    if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                                        if($findTax->is_dpp_nilai_Lain == 1){
                                            $hitung = Helpers::hitungIncludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                            $tax = $hitung[TypeEnum::PPN];
                                        }
                                        else {
                                            $hitung = Helpers::hitungIncludeTax($findTax->tax_percentage,$total);
                                            $tax = $hitung[TypeEnum::PPN];
                                        }
                                        $dpp = $total - $tax;
                                        $dppPiutang = $dppPiutang + $dpp;
                                        $totalTax = $totalTax + $tax;
                                    } else {
                                        if($findTax->is_dpp_nilai_Lain == 1){
                                            $hitung = Helpers::hitungExcludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                            $tax = $hitung[TypeEnum::PPN];
                                        }
                                        else {
                                            $hitung = Helpers::hitungExcludeTax($findTax->tax_percentage,$total);
                                            $tax = $hitung[TypeEnum::PPN];
                                        }
                                        $dppPiutang = $dppPiutang + $total;
                                    }

                                    $posisi = "kredit";
                                    if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                        $posisi = "debet";
                                    }
                                    $arrTax[] = array(
                                        'coa_id' => $findTax->sales_coa_id,
                                        'posisi' => $posisi,
                                        'nominal' => $tax,
                                        'nama_item' => $product->item_name,
                                        'id_item' => $item->id
                                    );
                                }
                            }
                        }
                    }
                }
                else {
                    $dpp = $item->subtotal;
                    $dppPiutang = $dppPiutang +  $item->subtotal;
                }
                if(!empty($dpp)){
                    $arrJurnalKredit = array(
                        'transaction_date' => $invDate,
                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                        'coa_id' => $coaPenjualan,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => $item->id,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $invNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => 0,
                        'kredit' => $dpp,
                        'note' => !empty($find->note) ? $find->note : 'Invoice penjualan Barang'.$noteProduct,
                    );
                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                }

            }
        }
        if(!empty($totalSediaan)){
            $arrJurnalDebet = array(
                'transaction_date' => $invDate,
                'transaction_datetime' => $invDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::DELIVERY_ORDER,
                'coa_id' => $coaBebanPokokPenjualan,
                'transaction_id' => $find->id,
                'transaction_sub_id' => $item->id,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $invNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $totalSediaan,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Invoice penjualan Barang'.$noteProduct,
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
        }
        return array(
            'dpp' => $dppPiutang,
            'tax' => $arrTax,
            'total_tax' => $totalTax
        );
    }

    public function postingJurnalBebanPokokPenjualan($find,$jurnalTransaksiRepo){
        $invDate = $find->invoice_date;
        $invNo = $find->invoice_no;
        $coaPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_PENJUALAN);
        $coaSediaanDalamPerjalanan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN_DALAM_PERJALANAN);
        $coaBebanPokoPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_POKOK_PENJUALAN);
        $dppPiutang = 0;
        $totalTax = 0;
        $arrTax = array();
        $findAllDelivered = SalesInvoicingDelivery::where(array('invoice_id' => $find->id))->with(['delivery','delivery.deliveryproduct','delivery.deliveryproduct.tax','delivery.deliveryproduct.tax.taxgroup','delivery.deliveryproduct.tax.taxgroup.tax'])->get();
        if(count($findAllDelivered) > 0){
            $totalSediaanDalamPerjalanan = 0;
            foreach ($findAllDelivered as $rec){
                $delivery = $rec->delivery;
                if(!empty($delivery)){
                    $arrProductRec = $delivery->deliveryproduct;
                    if(!empty($arrProductRec)){
                        foreach ($arrProductRec as $itemRec){
                            $dpp = 0;
                            $objTax = $itemRec->tax;
                            if(!empty($objTax)){
                                if($objTax->tax_type == VarType::TAX_TYPE_SINGLE){
                                    $posisi = "kredit";
                                    if($itemRec->tax_type == TypeEnum::TAX_TYPE_INCLUDE){
                                        if($objTax->is_dpp_nilai_Lain == 1){
                                            $hitung = Helpers::hitungIncludeTaxDppNilaiLain($itemRec->tax_percentage,$itemRec->subtotal);
                                            $tax = $hitung[TypeEnum::PPN];

                                        }
                                        else {
                                            $hitung = Helpers::hitungIncludeTax($itemRec->tax_percentage,$itemRec->subtotal);
                                            $tax = $hitung[TypeEnum::PPN];
                                        }
                                        $dpp = $itemRec->subtotal - $tax;
                                        $dppPiutang = $dppPiutang + $dpp;
                                        $totalTax = $totalTax + $tax;
                                    }
                                    else {
                                        if($objTax->is_dpp_nilai_Lain == 1){
                                            $hitung = Helpers::hitungExcludeTaxDppNilaiLain($itemRec->tax_percentage,$itemRec->subtotal);
                                            $tax = $hitung[TypeEnum::PPN];
                                        }
                                        else {
                                            $hitung = Helpers::hitungExcludeTax($itemRec->tax_percentage,$itemRec->subtotal);
                                            $tax = $hitung[TypeEnum::PPN];
                                        }
                                        $dpp = $itemRec->subtotal;
                                        $totalTax = $totalTax + $tax;
                                        $dppPiutang = $dppPiutang + $dpp;
                                    }

                                    if($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG){
                                        $posisi = "debet";
                                    }
                                    $arrTax[] = array(
                                        'coa_id' => $objTax->sales_coa_id,
                                        'posisi' => $posisi,
                                        'nominal' => $tax,
                                        'id_item' => $itemRec->id
                                    );
                                } else {
                                    $tagGroups = $objTax->taxgroup;
                                    if(!empty($tagGroups)){
                                        $total = $itemRec->subtotal;
                                        foreach ($tagGroups as $group){
                                            $findTax = $group->tax;
                                            if(!empty($findTax)){
                                                if($itemRec->tax_type == TypeEnum::TAX_TYPE_INCLUDE){
                                                    if($findTax->tax_sign == VarType::TAX_SIGN_PENAMBAH){
                                                        if($findTax->is_dpp_nilai_Lain == 1){
                                                            $hitung = Helpers::hitungIncludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                                            $tax = $hitung[TypeEnum::PPN];
                                                        }
                                                        else {
                                                            $hitung = Helpers::hitungIncludeTax($findTax->tax_percentage,$total);
                                                            $tax = $hitung[TypeEnum::PPN];
                                                        }
                                                        $dppItem = $total - $tax;
                                                        $dppPiutang = $dppPiutang + $dppItem;
                                                        $totalTax = $totalTax + $tax;
                                                        $dpp = $dpp + $dppItem;
                                                    }
                                                } else {
                                                    $dpp = $dpp + $itemRec->subtotal;
                                                    if($findTax->is_dpp_nilai_Lain == 1){
                                                        $hitung = Helpers::hitungExcludeTaxDppNilaiLain($findTax->tax_percentage,$itemRec->subtotal);
                                                        $tax = $hitung[TypeEnum::PPN];
                                                    }
                                                    else {
                                                        $hitung = Helpers::hitungExcludeTax($findTax->tax_percentage,$itemRec->subtotal);
                                                        $tax = $hitung[TypeEnum::PPN];
                                                    }
                                                    $totalTax = $totalTax + $tax;
                                                    $dppPiutang = $dppPiutang + $itemRec->subtotal;
                                                }

                                                $posisi = "kredit";
                                                if($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG){
                                                    $posisi = "debet";
                                                }
                                                $arrTax[] = array(
                                                    'coa_id' => $findTax->sales_coa_id,
                                                    'posisi' => $posisi,
                                                    'nominal' => $tax,
                                                    'id_item' => $itemRec->id
                                                );
                                            }
                                        }
                                    }
                                }
                            } else {
                                $dpp = $itemRec->subtotal;
                                $dppPiutang = $dppPiutang +  $itemRec->subtotal;
                            }
                            if(!empty($dpp)){
                                $arrJurnalKredit = array(
                                    'transaction_date' => $invDate,
                                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                                    'created_by' => $find->created_by,
                                    'updated_by' => $find->created_by,
                                    'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                                    'coa_id' => $coaPenjualan,
                                    'transaction_id' => $find->id,
                                    'transaction_sub_id' => $itemRec->id,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                    'transaction_no' => $invNo,
                                    'transaction_status' => JurnalStatusEnum::OK,
                                    'debet' => 0,
                                    'kredit' => $dpp,
                                    'note' => !empty($find->note) ? $find->note : 'Invoice penjualan Barang',
                                );
                                $jurnalTransaksiRepo->create($arrJurnalKredit);
                            }

                        }
                    }
                    DeliveryRepo::changeStatusDelivery($delivery->id);
                    $nilaiSediaanDalamPerjalanan = DeliveryRepo::getValueSediaanDalamPerjalan($delivery->id, $coaSediaanDalamPerjalanan);
                    if(!empty($nilaiSediaanDalamPerjalanan)){
                        $arrJurnalDebet = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                            'coa_id' => $coaSediaanDalamPerjalanan,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $rec->id,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $nilaiSediaanDalamPerjalanan,
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);
                    }

                    $totalSediaanDalamPerjalanan = $totalSediaanDalamPerjalanan + $nilaiSediaanDalamPerjalanan;
                }
                //ambil nilai

            }
            if(!empty($totalSediaanDalamPerjalanan)){
                $arrJurnalKredit = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                    'coa_id' => $coaBebanPokoPenjualan,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => $rec->id,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => 0,
                    'kredit' => $totalSediaanDalamPerjalanan,
                    'note' => !empty($find->note) ? $find->note : 'Invoice Penjualan',
                );
                $jurnalTransaksiRepo->create($arrJurnalKredit);
            }
        }
        return array(
            'dpp' => $dppPiutang,
            'tax' => $arrTax,
            'total_tax' => $totalTax
        );
    }

    public function getPaymentList($idInvoice){
        $findPayment = SalesPaymentInvoice::where(array('invoice_id' => $idInvoice))->orderBy('payment_date','DESC')->with(['salespayment','retur','jurnal'])->get();
        return $findPayment;
    }

    public function getDpListBy($idInvoice){
        $findDp = SalesInvoicingDp::where(array('invoice_id' => $idInvoice))->with(['downpayment'])->get();
        $arrDp = array();
        if(!empty($findDp)){
            foreach ($findDp as $dp){
                $arrDp[] = $dp->downpayment;
            }
        }
        return $arrDp;
    }

    public static function getStatusInvoice($idInvoice){
        $find = (new self(new SalesInvoicing()))->findOne($idInvoice);
        if(!empty($find)) {
            return $find->invoice_status;
        } else {
            return "";
        }
    }

    public static function changeStatusInvoice($idInvoice): void
    {
        $invoiceRepo = (new self(new SalesInvoicing()));
        $paymentInvoiceRepo = new PaymentInvoiceRepo(new SalesPaymentInvoice());
        $findInvoice = $invoiceRepo->findOne($idInvoice);
        $paid = $paymentInvoiceRepo->getAllPaymentByInvoiceId($idInvoice);
        if($paid == $findInvoice->grandtotal) {
            $invoiceRepo->update(array('invoice_status' => StatusEnum::LUNAS), $idInvoice);
        }
    }

    public static function insertIntoPaymentFromRetur($idInvoice,$returId,$returDate,$total){
        if(!empty($idInvoice)){
            $findInvoice = (new self(new SalesInvoicing()))->findOne($idInvoice);
            if(!empty($findInvoice)){
                if($findInvoice->invoice_status == StatusEnum::BELUM_LUNAS){
                    $arrInvoice = array(
                        'invoice_no' => $findInvoice->invoice_no,
                        'total_payment' => Utility::remove_commas($total),
                        'payment_date' => $returDate,
                        'total_discount' => '0',
                        'coa_id_discount' => "",
                        'invoice_id' => $findInvoice->id,
                        'payment_id' => '0',
                        'jurnal_id' => 0,
                        'vendor_id' => $findInvoice->vendor_id,
                        'retur_id' => $returId,
                        'total_overpayment' => 0,
                        'coa_id_overpayment' => ""
                    );
                    SalesPaymentInvoice::create($arrInvoice);
                }

            }
        }
    }

    public static function getTotalInvoiceBySaldoAwalCoaId($coaId)
    {
        $getTotal = SalesInvoicing::where(array('coa_id' => $coaId, 'input_type' => InputType::SALDO_AWAL))->sum('grandtotal');
        return $getTotal;
    }

    public static function sumGrandTotalByVendor($vendorId, $dari, $sampai='', $sign='between'){
        if($sign == 'between') {
            $total = SalesInvoicing::where([['vendor_id', '=', $vendorId]])->whereBetween('invoice_date',[$dari,$sampai])->sum('grandtotal');
        } else{
            $total = SalesInvoicing::where([['invoice_date', $sign, $dari], ['vendor_id', '=', $vendorId]])->sum('grandtotal');
        }
        return $total;
    }
}
