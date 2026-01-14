<?php
namespace Icso\Accounting\Repositories\Akuntansi\Jurnal;


use Exception;
use Faker\Core\File;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Models\Akuntansi\BukuPembantu;
use Icso\Accounting\Models\Akuntansi\Jurnal;
use Icso\Accounting\Models\Akuntansi\JurnalAkun;
use Icso\Accounting\Models\Akuntansi\JurnalMeta;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Akuntansi\PelunasanBukuPembantu;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePayment;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePaymentInvoice;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPayment;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentInvoice;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\BukuPembantuRepo;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Akuntansi\PelunasanBukuPembantuRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentInvoiceRepo;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\JurnalType;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Icso\Accounting\Utils\VendorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JurnalRepo extends ElequentRepository
{

    protected $model;

    public function __construct(Jurnal $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Jurnal();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('jurnal_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use ($where) {
            foreach ($where as $key => $value) {
                if ($key == 'coa_id') {
                    // Apply the coa_id filter using whereHas
                    $query->whereHas('jurnal_akun', function ($q) use ($value) {
                        $q->where('coa_id', $value);
                    });
                } elseif ($key == 'jurnal_date') {
                    // Assuming $value is an array [$fromDate, $untilDate]
                    $query->whereBetween('jurnal_date', $value);
                } else {
                    $query->where($key, $value);
                }
            }
        })->orderBy('jurnal_date','desc')->with(['coa'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Jurnal();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('jurnal_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use ($where) {
            foreach ($where as $key => $value) {
                if ($key == 'coa_id') {
                    // Apply the coa_id filter using whereHas
                    $query->whereHas('jurnal_akun', function ($q) use ($value) {
                        $q->where('coa_id', $value);
                    });
                } elseif ($key == 'jurnal_date') {
                    // Assuming $value is an array [$fromDate, $untilDate]
                    $query->whereBetween('jurnal_date', $value);
                } else {
                    $query->where($key, $value);
                }
            }
        })->orderBy('jurnal_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.

        $id = $request->id;
        $jurnalNo = $request->jurnal_no;
        if(empty($jurnalNo)){
            if($request->jurnal_type == JurnalType::JURNAL_UMUM){
                $jurnalNo = self::generateCodeTransaction(new Jurnal(),KeyNomor::KEY_JURNAL_UMUM,'jurnal_no','jurnal_date');
            } else if($request->jurnal_type == JurnalType::JURNAL_KAS){
                $jurnalNo = self::generateCodeTransaction(new Jurnal(),KeyNomor::KEY_JURNAL_KAS,'jurnal_no','jurnal_date');
            } else if($request->jurnal_type == JurnalType::JURNAL_BANK){
                $jurnalNo = self::generateCodeTransaction(new Jurnal(),KeyNomor::KEY_JURNAL_BANK,'jurnal_no','jurnal_date');
            } else {
                $jurnalNo = self::generateCodeTransaction(new Jurnal(),KeyNomor::KEY_JURNAL_GIRO,'jurnal_no','jurnal_date');
            }
        }
        $jurnalDate = Utility::changeDateFormat($request->jurnal_date);
        $userId = $request->user_id;
        $dokumen = '';
        $arrData = array(
            'jurnal_date' => $jurnalDate,
            'jurnal_no' => $jurnalNo,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
            'note' => (!empty($request->note) ? $request->note : ''),
            'jurnal_type' => $request->jurnal_type,
            'status_jurnal' => JurnalStatusEnum::OK,
            'coa_id' => !empty($request->coa_id) ? $request->coa_id : '0',
            'transaction_type' => !empty($request->transaction_type) ? $request->transaction_type : '',
            'person' => !empty($request->person) ? $request->person : '',
            'nominal' => !empty($request->nominal) ? Utility::remove_commas($request->nominal) : '0',
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['document'] = $dokumen;
                $arrData['reason'] = '';
                $arrData['created_by'] = $userId;
                $res = $this->create($arrData);
            } else {
                if (!empty($dokumen)) {
                    $arrData['document'] = $dokumen;
                }
                $res = $this->update($arrData,$id);
            }
            if ($res) {
                $jurnalId = '0';
                if (!empty($id)) {
                    $this->deleteAdditionalData($id);
                    $jurnalId = $id;
                } else {
                    $jurnalId = $res->id;
                }
                if(empty($request->jurnal_no)){
                    $request->jurnal_no = $jurnalNo;
                }
                if($request->jurnal_type == JurnalType::JURNAL_UMUM) {
                    $this->storeJurnalUmum($request,$jurnalId);
                }
                else {
                    $this->storeKasBank($request, $jurnalId);
                }
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)){
                    if(count($uploadedFiles) > 0){
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            $resUpload = $fileUpload->upload($file,tenant(), $request->user_id);
                            if($resUpload){
                                $arrUpload = array(
                                    'jurnal_id' => $jurnalId,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                JurnalMeta::create($arrUpload);
                            }
                        }
                    }
                }


                DB::commit();
                return true;
            }
            else {
                return false;
            }

        }catch (\Exception $e) {
            // Rollback Transaction
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function delete($id)
    {
        // TODO: Implement delete() method.
        DB::beginTransaction();
        try
        {
            $this->deleteAdditionalData($id);
            $this->deleteByWhere(array('id' => $id));
            DB::commit();
            return true;
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollback();
            return false;
        }
    }



    public function deleteAdditionalData($id)
    {
        // TODO: Implement deleteAdditionalData() method.
        $jurnalAkunRepo = new JurnalAkunRepo(new JurnalAkun());
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $jurnalAkunRepo->deleteByWhere(array('jurnal_id' => $id));
        $jurnalTransaksiRepo->deleteByWhere(array('transaction_code' => TransactionsCode::JURNAL,'transaction_id' => $id));
        Inventory::where(array('transaction_code' => TransactionsCode::JURNAL, 'transaction_id' => $id))->delete();
        PurchaseInvoicing::where(array('input_type' => TransactionsCode::JURNAL, 'jurnal_id' => $id))->delete();
        SalesInvoicing::where(array('input_type' => TransactionsCode::JURNAL, 'jurnal_id' => $id))->delete();
        JurnalMeta::where(array('jurnal_id' => $id))->delete();
    }

    public function storeKasBank(Request $request, $jurnalId)
    {
        // TODO: Implement storeKasBank() method.
        $jurnalAkunRepo = new JurnalAkunRepo(new JurnalAkun());
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $jurnalNo = $request->jurnal_no;
        $jurnalDate = Utility::changeDateFormat($request->jurnal_date);
        $jurnalAkun = json_decode(json_encode($request->jurnal_akun));
        $jurnalDateTime = $jurnalDate." ".date("H:i:s");
        $userId = $request->user_id;
        $totalDebet = 0;
        $totalKredit = 0;
        if ($request->transaction_type == JurnalType::INCOME_TYPE) {
            $totalDebet = Utility::remove_commas($request->nominal);
        } else {
            $totalKredit = Utility::remove_commas($request->nominal);
        }
        $arrJurnalAkun = array(
            'transaction_date' => $jurnalDate,
            'transaction_datetime' => $jurnalDateTime,
            'created_by' => $userId,
            'updated_by' => $userId,
            'transaction_code' => TransactionsCode::JURNAL,
            'coa_id' => $request->coa_id,
            'transaction_id' => $jurnalId,
            'transaction_sub_id' => '0',
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
            'transaction_status' => JurnalStatusEnum::OK,
            'transaction_no' => $jurnalNo,
            'debet' => $totalDebet,
            'kredit' => $totalKredit,
            'note' => !empty($request->note) ? $request->note : '',
        );
        $jurnalTransaksiRepo->create($arrJurnalAkun);
        if (count($jurnalAkun) > 0) {
            foreach ($jurnalAkun as $i => $val)
            {
                $nomInput = !empty($val->nominal) ? Utility::remove_commas($val->nominal) : '0';
                $findCoa = Coa::where('id',$val->coa_id)->first();
                $dataSession = (!empty($val->data_session) ? json_encode($val->data_session) : '');
                if(!empty($findCoa)){
                    if($findCoa->connect_db == 0){
                        $dataSession = '';
                    }
                }
                $arrItemAkun = array(
                    'jurnal_id' => $jurnalId,
                    'coa_id' => $val->coa_id,
                    'data_sess' => $dataSession,
                    'debet' => '0',
                    'kredit' => '0',
                    'nominal' => $nomInput,
                    'note' => (!empty($val->note) ? $val->note : ''),
                );
                $resItemAkun = $jurnalAkunRepo->create($arrItemAkun);
                $nomDebet = 0;
                $nomKredit = 0;
                if ($request->transaction_type == JurnalType::INCOME_TYPE) {
                    $nomKredit = $nomInput;
                } else {
                    $nomDebet = $nomInput;
                }
                $arrJurnal = array(
                    'transaction_date' => $jurnalDate,
                    'transaction_datetime' => $jurnalDateTime,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'transaction_code' => TransactionsCode::JURNAL,
                    'coa_id' => $val->coa_id,
                    'transaction_id' => $jurnalId,
                    'transaction_sub_id' => $resItemAkun->id,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_status' => JurnalStatusEnum::OK,
                    'transaction_no' => $jurnalNo,
                    'debet' => $nomDebet,
                    'kredit' => $nomKredit,
                    'note' => !empty($val->note) ? $val->note : '',
                );
                $jurnalTransaksiRepo->create($arrJurnal);

                //data session adalah data baik pelunasan atau penambahan
                if (!empty($val->data_session)) {
                    $sess = $val->data_session;
                    $passData = array(
                        'debet' => $nomDebet,
                        'kredit' => $nomKredit,
                        'jurnal_id' => $jurnalId,
                        'jurnal_no' => $jurnalNo,
                        'jurnal_date' => $jurnalDate,
                        'user_id' => $userId,
                        'coa_id' => $val->coa_id,
                        'invoice_type' => 'item',
                        'jurnal_akun_id' => $resItemAkun->id
                    );
                    $this->createDataSession($sess, $passData);
                }
            }
        }
    }

    public function createDataSession($sess, array $data)
    {
        // TODO: Implement createDataSession() method.
        $salesInvoiceRepo = new InvoiceRepo(new SalesInvoicing());
        $purchaseInvoiceRepo = new \Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo(new PurchaseInvoicing());
        $salesPaymentRepo = new PaymentRepo(new SalesPayment());
        $purchasePaymentRepo = new \Icso\Accounting\Repositories\Pembelian\Payment\PaymentRepo(new PurchasePayment());
        $salesPaymentInvoiceRepo = new PaymentInvoiceRepo(new SalesPaymentInvoice());
        $purchasePaymentInvoiceRepo = new \Icso\Accounting\Repositories\Pembelian\Payment\PaymentInvoiceRepo(new PurchasePaymentInvoice());
        if($sess->var_kontak == VendorType::CUSTOMER) {
            if ($sess->var_type == VarType::PELUNASAN) {
                $customerId = $sess->var_vendor->id;
               // $customerName = $sess->var_customer->customer_name;
                $invoiceNo = $sess->var_no_ref;
                $invoiceId = '0';
                $findInvoice = $salesInvoiceRepo->findWhere(array('invoice_no' => $invoiceNo));
                if(!empty($findInvoice)){
                    $invoiceId = $findInvoice->id;
                }
                $nominal = 0;
                if($data['debet'] != '0')
                {
                    $nominal = $data['debet'];
                }
                if($data['kredit'] != '0')
                {
                    $nominal = $data['kredit'];
                }
                if(!empty($invoiceNo)) {
                    $request = new Request();
                    $request->payment_date = $data['jurnal_date'];
                    $request->invoice_no = $invoiceNo;
                    $request->invoice_id = $invoiceId;
                    $request->jurnal_id = $data['jurnal_id'];
                    $request->payment_no = $data['jurnal_no'];
                    $request->jurnal_akun_id = $data['jurnal_akun_id'];
                    $request->vendor_id = $customerId;
                    $request->total_payment = $nominal;
                    $resPayment = $salesPaymentInvoiceRepo->store($request);
                }
            }
            else {
                $customerId = $sess->var_vendor->id;
                $customerName = $sess->var_vendor->vendor_name;
                $invoiceNo = $sess->var_no_ref;
                $ket = $sess->var_note_ref;
                $nominal = 0;
                if($data['debet'] != '0')
                {
                    $nominal = $data['debet'];
                }
                if($data['kredit'] != '0')
                {
                    $nominal = $data['kredit'];
                }
                if(!empty($invoiceNo)) {
                    $request = new Request();
                    $request->jurnal_id = $data['jurnal_id'];
                    $request->note = $ket;
                    $request->subtotal = $nominal;
                    $request->grandtotal = $nominal;
                    $request->customer_name = $customerName;
                    $request->vendor_id = $customerId;
                    $request->invoice_no = $invoiceNo;
                    $request->invoice_date = $data['jurnal_date'];
                    $request->user_id = $data['user_id'];
                    $request->coa_id = $data['coa_id'];
                    $request->invoice_type = 'item';
                    $request->input_type = InputType::SALES;
                    $resSalesInvoice = $salesInvoiceRepo->store($request);
                }
            }
        } else if($sess->var_kontak == 'supplier'){
            if($sess->var_type == VarType::PELUNASAN)
            {
                $supplierId = $sess->var_vendor->id;
                $invoiceNo = $sess->var_no_ref;
                $invoiceId = '0';
                $findPurchaseInvoice = $purchaseInvoiceRepo->findWhere(array('invoice_no' => $invoiceNo));
                if(!empty($findPurchaseInvoice)){
                    $invoiceId = $findPurchaseInvoice->id;
                }
                $nominal = 0;
                if($data['debet'] != '0')
                {
                    $nominal = $data['debet'];
                }
                if($data['kredit'] != '0')
                {
                    $nominal = $data['kredit'];
                }
                $request = new Request();
                $request->payment_date = $data['jurnal_date'];
                $request->invoice_no = $invoiceNo;
                $request->invoice_id = $invoiceId;
                $request->jurnal_id = $data['jurnal_id'];
                $request->payment_no = $data['jurnal_no'];
                $request->jurnal_akun_id = $data['jurnal_akun_id'];
                $request->vendor_id = $supplierId;
                $request->total_payment = $nominal;
                $resPayment = $purchasePaymentInvoiceRepo->store($request);
            } else {
                $supplierId = $sess->var_vendor->id;
                $supplierName = $sess->var_vendor->vendor_name;
                $invoiceNo = $sess->var_no_ref;
                $ket = $sess->var_note_ref;
                $nominal = 0;
                if($data['debet'] != '0')
                {
                    $nominal = $data['debet'];
                }
                if($data['kredit'] != '0')
                {
                    $nominal = $data['kredit'];
                }
                $request = new Request();
                $request->jurnal_id = $data['jurnal_id'];
                $request->note = $ket;
                $request->subtotal = $nominal;
                $request->grandtotal = $nominal;
                $request->vendor_id = $supplierId;
                $request->supplier_name = $supplierName;
                $request->invoice_no = $invoiceNo;
                $request->invoice_date = $data['jurnal_date'];
                $request->due_date = $data['jurnal_date'];
                $request->user_id = $data['user_id'];
                $request->coa_id = $data['coa_id'];
                $request->invoice_type = $data['invoice_type'];
                $request->input_type = InputType::JURNAL;
                $resPurchaseInvoice = $purchaseInvoiceRepo->store($request);
            }
        }
        else if($sess->var_kontak == 'custom')
        {
            $bukuPembantuRepo = new BukuPembantuRepo(new BukuPembantu());
            if($sess->var_type == VarType::PENAMBAHAN) {
                $fieldName = $sess->var_custom;
                $noRef = $sess->var_no_ref_custom;
                $note = $sess->var_note_ref_custom;
                $nominal = 0;
                if ($data['debet'] != '0') {
                    $nominal = $data['debet'];
                }
                if ($data['kredit'] != '0') {
                    $nominal = $data['kredit'];
                }
                $request = new Request();
                $request->field_name = $fieldName;
                $request->no_ref = $noRef;
                $request->note = $note;
                $request->input_type = InputType::JURNAL;
                $request->nominal = $nominal;
                $request->coa_id = $data['coa_id'];
                $request->user_id = $data['user_id'];
                $request->jurnal_date = $data['jurnal_date'];
                $request->jurnal_id = $data['jurnal_id'];
                $request->jurnal_akun_id = $data['jurnal_akun_id'];
                $resBukuPembantu = $bukuPembantuRepo->store($request);
            } else {
                $pelunasanBukuPembantuRepo = new PelunasanBukuPembantuRepo(new PelunasanBukuPembantu());
                $nominal = 0;
                if ($data['debet'] != '0') {
                    $nominal = $data['debet'];
                }
                if ($data['kredit'] != '0') {
                    $nominal = $data['kredit'];
                }
                $request = new Request();
                $request->ref_no = $sess->var_no_ref_custom;
                $request->note = "";
                $request->buku_pembantu_id = $sess->var_buku_pembantu->id;
                $request->nominal = $nominal;
                $request->user_id = $data['user_id'];
                $request->payment_date = $data['jurnal_date'];
                $request->jurnal_id = $data['jurnal_id'];
                $request->payment_no = $data['jurnal_no'];
                $request->jurnal_akun_id = $data['jurnal_akun_id'];
                $resBukuPembantu = $pelunasanBukuPembantuRepo->store($request);
            }
        } else if($sess->var_kontak == 'persediaan')
        {
            $inventoryRepo = new InventoryRepo(new Inventory());
            $productId = $sess->var_barang->id;
            $unitId = $sess->var_barang->unit_id;
            $warehouseId = $sess->var_warehouse->id;
            $qty = $sess->var_qty;
            $hpp = $sess->var_hpp;
            $note = !empty($sess->var_note_ref) ? $sess->var_note_ref : "";

            $qtyIn = 0;
            $qtyOut = 0;
            if($sess->var_type == VarType::PENAMBAHAN)
            {
                $qtyIn = $qty;
            }
            else
            {
                $qtyOut = $qty;
            }
            $request = new Request();
            $request->inventory_date = $data['jurnal_date']." ".date("H:i:s");
            $request->transaction_id = $data['jurnal_id'];
            $request->transaction_sub_id = $data['jurnal_akun_id'];
            $request->product_id = $productId;
            $request->user_id = $data['user_id'];
            $request->coa_id = $data['coa_id'];
            $request->unit_id = $unitId;
            $request->price = $hpp;
            $request->qty_in = $qtyIn;
            $request->qty_out = $qtyOut;
            $request->note = $note;
            $request->warehouse_id = $warehouseId;
            $request->transaction_code = TransactionsCode::JURNAL;
            $resInventory = $inventoryRepo->store($request);
        }

    }

    public function storeJurnalUmum(Request $request, $jurnalId)
    {
        // TODO: Implement storeJurnalUmum() method.
        $jurnalNo = $request->jurnal_no;
        $jurnalDate = Utility::changeDateFormat($request->jurnal_date);
        $jurnalDateTime = $jurnalDate." ".date("H:i:s");
        $userId = $request->user_id;
        $jurnalAkunRepo = new JurnalAkunRepo(new JurnalAkun());
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $jurnalAkun = json_decode(json_encode($request->jurnal_akun));
        if (count($jurnalAkun) > 0) {
            foreach ($jurnalAkun as $i => $val)
            {
               // echo $val['coa_id'];
                $nomDebet = !empty($val->debet) ? Utility::remove_commas($val->debet) : '0';
                $nomKredit = !empty($val->kredit) ? Utility::remove_commas($val->kredit) : '0';
                $findCoa = Coa::where('id',$val->coa_id)->first();
                $dataSession = (!empty($val->data_session) ? json_encode($val->data_session) : '');
                if(!empty($findCoa)){
                    if($findCoa->connect_db == 0){
                        $dataSession = '';
                    }
                }
                $arrItemAkun = array(
                    'jurnal_id' => $jurnalId,
                    'coa_id' => $val->coa_id,
                    'data_sess' => $dataSession,
                    'debet' => $nomDebet,
                    'kredit' => $nomKredit,
                    'nominal' => '0',
                    'note' => (!empty($val->note) ? $val->note : ''),
                );
                $resItemAkun = $jurnalAkunRepo->create($arrItemAkun);
                $arrJurnal = array(
                    'transaction_date' => $jurnalDate,
                    'transaction_datetime' => $jurnalDateTime,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'transaction_code' => TransactionsCode::JURNAL,
                    'coa_id' => $val->coa_id,
                    'transaction_id' => $jurnalId,
                    'transaction_sub_id' => $resItemAkun->id,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $jurnalNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => $nomDebet,
                    'kredit' => $nomKredit,
                    'note' => !empty($val->note) ? $val->note : '',
                );
                $jurnalTransaksiRepo->create($arrJurnal);

                //data session adalah data baik pelunasan atau penambahan
                if (!empty($val->data_session)) {
                    $sess = $val->data_session;
                    $passData = array(
                        'debet' => $nomDebet,
                        'kredit' => $nomKredit,
                        'jurnal_id' => $jurnalId,
                        'jurnal_date' => $jurnalDate,
                        'user_id' => $userId,
                        'coa_id' => $val->coa_id,
                        'jurnal_akun_id' => $resItemAkun->id
                    );
                    $this->createDataSession($sess, $passData);
                }
            }
        }
    }

    public function generateNumber($str)
    {
        // TODO: Implement generateNumber() method.
        if(empty($str)){

        }
        return $str;
    }
}
