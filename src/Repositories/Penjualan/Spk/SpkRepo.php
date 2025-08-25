<?php

namespace Icso\Accounting\Repositories\Penjualan\Spk;

use App\Enums\StatusEnum;
use App\Models\Tenant\Pembelian\Bast\PurchaseBast;
use App\Models\Tenant\Pembelian\Bast\PurchaseBastProduct;
use App\Models\Tenant\Penjualan\Pengiriman\SalesDeliveryMeta;
use App\Models\Tenant\Penjualan\Spk\SalesSpk;
use App\Models\Tenant\Penjualan\Spk\SalesSpkMeta;
use App\Models\Tenant\Penjualan\Spk\SalesSpkProduct;
use App\Repositories\ElequentRepository;
use App\Services\FileUploadService;
use App\Utils\KeyNomor;
use App\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpkRepo extends ElequentRepository
{
    protected $model;

    public function __construct(SalesSpk $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        // $paymentInvoiceRepo = new PaymentInvoiceRepo(new PurchasePaymentInvoice());
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where(function ($que) use($search){
                $que->where('spk_no', 'like', '%' .$search. '%');
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
        })->with(['vendor','order','spkproduct','spkproduct.product','spkproduct.product.unit'])->orderBy('spk_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where(function ($que) use($search){
                $que->where('spk_no', 'like', '%' .$search. '%');
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
        $spkNo = $request->spk_no;
        if(empty($bastNo)){
            $spkNo = self::generateCodeTransaction(new SalesSpk(),KeyNomor::NO_SPK,'spk_no','spk_date');
        }
        $spkDate = !empty($request->spk_date) ? Utility::changeDateFormat($request->spk_date) : date('Y-m-d');
        $orderId = $request->order_id;
        $vendorId = $request->vendor_id;
        $note = $request->note;
        $userId = $request->user_id;
        $arrData = array(
            'spk_no' => $spkNo,
            'spk_date' => $spkDate,
            'order_id' => $orderId,
            'vendor_id' => $vendorId,
            'note' => $note,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        );
        try {
            if(empty($id)){
                $arrData['reason'] = '';
                $arrData['spk_status'] = StatusEnum::SELESAI;
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $res = $this->create($arrData);
            } else{
                $res = $this->update($arrData, $id);
            }
            if($res){
                if(!empty($id)){
                    $this->deleteAdditional($id);
                    $idSpk = $id;
                } else {
                    $idSpk = $res->id;
                }
                $products = json_decode(json_encode($request->spkproduct));
                if (count($products) > 0) {
                    foreach ($products as $item) {
                        $arrItem = array(
                            'spk_id' => $idSpk,
                            'qty' => $item->qty,
                            'product_id' => $item->product_id,
                            'order_product_id' => $item->order_product_id
                        );
                        SalesSpkProduct::create($arrItem);
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
                                    'spk_id' => $idSpk,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                SalesSpkMeta::create($arrUpload);
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
        }
        catch (\Exception $e){
            echo $e->getMessage();
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($id)
    {
        SalesSpkProduct::where(array('spk_id' =>  $id))->delete();
        SalesSpkMeta::where(array('spk_id' =>  $id))->delete();

    }

    public function getSpkProduct($spkId,$idProduct)
    {
        $totalKirim = SalesSpkProduct::where(array('product_id' => $idProduct, 'spk_id' => $spkId))->sum('qty');
        return $totalKirim;
    }
}
