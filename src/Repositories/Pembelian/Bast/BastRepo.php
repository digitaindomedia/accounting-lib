<?php

namespace Icso\Accounting\Repositories\Pembelian\Bast;


use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Pembelian\Bast\PurchaseBast;
use Icso\Accounting\Models\Pembelian\Bast\PurchaseBastMeta;
use Icso\Accounting\Models\Pembelian\Bast\PurchaseBastProduct;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\RequestAuditHelper;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BastRepo extends ElequentRepository
{
    protected $model;
    protected ActivityLogService $activityLog;

    public function __construct(PurchaseBast $model, ActivityLogService $activityLog)
    {
        parent::__construct($model);
        $this->model = $model;
        $this->activityLog = $activityLog;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        // $paymentInvoiceRepo = new PaymentInvoiceRepo(new PurchasePaymentInvoice());
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where(function ($que) use($search){
                $que->where('bast_no', 'like', '%' .$search. '%');
                $que->orWhereHas('order', function ($qOrder) use ($search) {
                    $qOrder->where('order_no', 'like', '%' .$search. '%');
                });
            });
        })->when(!empty($where), function ($query) use($where){
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
        })->with(['vendor','order','bastproduct','bastproduct.tax'])->orderBy('bast_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where(function ($que) use($search){
                $que->where('bast_no', 'like', '%' .$search. '%');
                $que->orWhereHas('order', function ($qOrder) use ($search) {
                    $qOrder->where('order_no', 'like', '%' .$search. '%');
                });
            });
        })->when(!empty($where), function ($query) use($where){
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
        })->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = []): bool
    {
        $id = $request->id;
        $userId = $request->user_id;
        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id, [], ['vendor','order','bastproduct','bastproduct.orderproduct','bastproduct.tax'])?->toArray();
        }
        $bastNo = $request->bast_no;
        if(empty($bastNo)){
            $bastNo = self::generateCodeTransaction(new PurchaseBast(),KeyNomor::NO_BAST,'bast_no','bast_date');
        }
        $bastDate = !empty($request->bast_date) ? Utility::changeDateFormat($request->bast_date) : date('Y-m-d');
        $orderId = $request->order_id;
        $vendorId = $request->vendor_id;
        $note = $request->note;
        $arrData = array(
            'bast_no' => $bastNo,
            'bast_date' => $bastDate,
            'order_id' => $orderId,
            'vendor_id' => $vendorId,
            'note' => $note,
            'updated_by' => $userId,
            'updated_at' => now()
        );
        DB::beginTransaction();
        try {
            if(empty($id)){
                $arrData['reason'] = '';
                $arrData['bast_status'] = StatusEnum::OPEN;
                $arrData['created_at'] = now();
                $arrData['created_by'] = $userId;
                $res = $this->create($arrData);
                $action = 'Tambah data BAST pembelian dengan nomor ' . $bastNo;
            } else{
                $res = $this->update($arrData, $id);
                $action = 'Edit data BAST pembelian dengan nomor ' . $bastNo;
            }
            if($res){
                if(!empty($id)){
                    $this->deleteAdditional($id);
                    $idBast = $id;
                } else {
                    $idBast = $res->id;
                }
                $products = json_decode(json_encode($request->bastproduct));
                if (count($products) > 0) {
                    foreach ($products as $item) {
                        $getDetailHpp = $this->getHppPrice($item->qty, $item->order_product_id);
                        $arrItem = array(
                            'bast_id' => $idBast,
                            'qty' => $item->qty,
                            'qty_left' => $item->qty,
                            'service_name' => $item->service_name,
                            'order_product_id' => $item->order_product_id,
                            'hpp_price' => $getDetailHpp['hpp_price'],
                            'buy_price' => $getDetailHpp['buy_price'],
                            'tax_id' => $getDetailHpp['tax_id'],
                            'tax_percentage' => $getDetailHpp['tax_percentage'],
                            'discount' => $getDetailHpp['discount'],
                            'tax_type' => $getDetailHpp['tax_type'],
                            'discount_type' => $getDetailHpp['discount_type'],
                        );
                        PurchaseBastProduct::create($arrItem);
                    }
                }
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'bast_id' => $idBast,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                PurchaseBastMeta::create($arrUpload);
                            }
                        }
                    }
                }
                DB::commit();

                $this->activityLog->log([
                    'user_id' => $userId,
                    'action' => $action,
                    'model_type' => PurchaseBast::class,
                    'model_id' => $idBast,
                    'old_values' => $oldData,
                    'new_values' => $this->findOne($idBast, [], ['vendor','order','bastproduct','bastproduct.orderproduct','bastproduct.tax'])?->toArray(),
                    'request_payload' => RequestAuditHelper::sanitize($request),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return true;
            }
            else {
                return false;
            }
        }
        catch (\Exception $e){
            Log::error('[BastRepo][store] ' . $e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function getHppPrice($qty, $orderProductId){
        $subtotal = 0;
        $diskon = 0;
        $taxId = 0;
        $percentTax = 0;
        $taxType ="";
        $discountType = "";
        $price = 0;
        $findProductOrder = PurchaseOrderProduct::where(array('id' => $orderProductId))->first();
        if(!empty($findProductOrder)){
            $price = $findProductOrder->price;
            $percentTax = $findProductOrder->tax_percentage;
            $total = $qty * $price;
            $taxId = $findProductOrder->tax_id;
            $taxType = $findProductOrder->tax_type;
            $discountType = $findProductOrder->discount_type;
            $subtotal = $total;
            if(!empty($findProductOrder->discount)){
                if($findProductOrder->discount_type == TypeEnum::DISCOUNT_TYPE_PERCENT){
                    $diskon = ($findProductOrder->discount/100) * $total;
                } else {
                    $totalBawah = $findProductOrder->qty * $price;
                    $diskon = Helpers::hitungProporsi($total,$totalBawah,$findProductOrder->discount);
                }
                $total = $total - $diskon;
            }
            if(!empty($taxId)){
                $getDataTax = Helpers::hitungTaxDpp($total,$taxId,$taxType,$percentTax);
                if(!empty($getDataTax)){
                    $subtotal = $getDataTax[TypeEnum::DPP];
                }
            }
        }
        $hpp = $subtotal/$qty;
        return array(
            'subtotal' => $subtotal,
            'hpp_price' => $hpp,
            'buy_price' => $price,
            'tax_id' => $taxId,
            'tax_percentage' => $percentTax,
            'discount' => $diskon,
            'tax_type' => $taxType,
            'discount_type' => $discountType
        );

    }

    public function deleteAdditional($id)
    {
        PurchaseBastProduct::where(array('bast_id' => $id))->delete();
    }

    public function destroy(int $id, int $userId): bool
    {
        $bast = $this->findOne($id, [], ['vendor','order','bastproduct','bastproduct.orderproduct','bastproduct.tax']);
        if (!$bast) {
            return false;
        }

        $oldData = $bast->toArray();

        DB::beginTransaction();
        try {
            $this->deleteAdditional($id);
            $this->deleteByWhere(['id' => $id]);
            DB::commit();

            $bastNo = $oldData['bast_no'] ?? '';
            $this->activityLog->log([
                'user_id' => $userId,
                'action' => 'Hapus data BAST pembelian dengan nomor ' . $bastNo,
                'model_type' => PurchaseBast::class,
                'model_id' => $id,
                'old_values' => $oldData,
                'new_values' => null,
                'request_payload' => RequestAuditHelper::sanitize(request()),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[BastRepo][destroy] ' . $e->getMessage());
            return false;
        }
    }
}
