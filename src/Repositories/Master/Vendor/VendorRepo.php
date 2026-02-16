<?php
namespace Icso\Accounting\Repositories\Master\Vendor;

use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Master\VendorMeta;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\Constants;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VendorType;
use Illuminate\Http\Request;

class VendorRepo extends ElequentRepository
{

    protected $model;

    public function __construct(Vendor $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where(function($q) use($search){
                $q->where('vendor_name', 'like', '%' .$search. '%');
                $q->orWhere('vendor_code', 'like', '%' .$search. '%');
                $q->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                $q->orWhere('vendor_address', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('vendor_name','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where(function($q) use($search){
                $q->where('vendor_name', 'like', '%' .$search. '%');
                $q->orWhere('vendor_code', 'like', '%' .$search. '%');
                $q->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                $q->orWhere('vendor_address', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('vendor_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $vendorPhoto = '';
        $vendorCode = $request->vendor_code;
        if(empty($vendorCode)) {
            $vendorCode = $this->autoGenerateCode($request->vendor_name);
        }
        $arrData = array(
            'vendor_name' => $request->vendor_name,
            'vendor_code' => $vendorCode,
            'vendor_ktp' => (!empty($request->vendor_ktp) ? $request->vendor_ktp : ''),
            'vendor_address' => (!empty($request->vendor_address) ? $request->vendor_address : ''),
            'vendor_max' => '0',
            'vendor_duration' => !empty($request->vendor_duration) ? $request->vendor_duration : '0',
            'vendor_duration_by' => !empty($request->vendor_duration_by) ? $request->vendor_duration_by : '',
            'vendor_pkp_status' => !empty($request->vendor_pkp_status) ? $request->vendor_pkp_status : '',
            'vendor_company_name' => (!empty($request->vendor_company_name) ? $request->vendor_company_name : ''),
            'vendor_pkp_no' => (!empty($request->vendor_pkp_no) ? $request->vendor_pkp_no : ''),
            'vendor_pkp_date' => (!empty($request->vendor_pkp_date) ? Utility::changeDateFormat($request->vendor_pkp_date) : ''),
            'vendor_npwp' => (!empty($request->vendor_npwp) ? $request->vendor_npwp : ''),
            'coa_id' => !empty($request->coa_id) ? $request->coa_id : '0',
            'country_id' => !empty($request->country_id) ? $request->country_id : '0',
            'kawasan_berikat' => !empty($request->kawasan_berikat) ? $request->kawasan_berikat : '',
            'aging' => !empty($request->aging) ? $request->aging : '0',
            'aging_by' => !empty($request->aging_by) ? $request->aging_by : '',
            'vendor_email' => (!empty($request->vendor_email) ? $request->vendor_email : ''),
            'vendor_phone' => (!empty($request->vendor_phone) ? $request->vendor_phone : ''),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $request->user_id
        );
        if(empty($id)) {
            $arrData['vendor_photo'] = '';
            $arrData['vendor_status'] = Constants::AKTIF;
            $arrData['vendor_type'] = $request->vendor_type;
            $arrData['created_by'] = $request->user_id;
            $arrData['created_at'] = date('Y-m-d H:i:s');

            $vendorId =  $this->create($arrData);
            $this->insertVendorMeta($vendorId->id, $request->vendor_meta);
            return $vendorId;
        } else {
            if(!empty($vendorPhoto)) {
                $arrData['vendor_photo'] = $vendorPhoto;
            }
            $updateRes =  $this->update($arrData,$id);
            $this->insertVendorMeta($id, $request->vendor_meta);
            return $updateRes;
        }
    }

    private function insertVendorMeta($vendorId, $arrVendorMeta)
    {
        if(!empty($arrVendorMeta)){
            VendorMeta::where('vendor_id', $vendorId)->delete();
            foreach ($arrVendorMeta as $value) {
                VendorMeta::create([
                    'vendor_id' => $vendorId,
                    'meta_key' => 'shipping_address',
                    'meta_value' => $value['meta_value'],
                ]);
            }
        }
    }

    public function autoGenerateCode($str)
    {
        // TODO: Implement autoGenerateCode() method.
        $first_letter = 'A';
        if(!empty($str))
        {
            $first_letter = substr($str,0,1);
        }
        $nextId = $first_letter.'00001';
        $res = Vendor::orderBy('id','desc')->first();
        if(!empty($res)) {
            $idPostFix = $res->id + 1;
            $nextId = $first_letter.STR_PAD((string)$idPostFix,5,"0",STR_PAD_LEFT);
        }
        return $nextId;
    }

    public function getAllData($search, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where(function($q) use($search){
                $q->where('vendor_name', 'like', '%' .$search. '%');
                $q->orWhere('vendor_code', 'like', '%' .$search. '%');
                $q->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                $q->orWhere('vendor_address', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('vendor_name','asc');
        return $dataSet;
    }

    public static function getVendorId($kodeSupplier)
    {
        $vendor = Vendor::where('vendor_code', $kodeSupplier)->first();
        return $vendor ? $vendor->id : 0;
    }

    public static function getSupplierId($kodeSupplier)
    {
        $vendor = Vendor::where('vendor_code', $kodeSupplier)->where('vendor_type', VendorType::SUPPLIER)->first();
        return $vendor ? $vendor->id : 0;
    }

    public static function getCustomerId($kodeCustomer)
    {
        $vendor = Vendor::where('vendor_code', $kodeCustomer)->where('vendor_type', VendorType::CUSTOMER)->first();
        return $vendor ? $vendor->id : 0;
    }

}
