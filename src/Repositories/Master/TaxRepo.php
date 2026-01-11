<?php
namespace Icso\Accounting\Repositories\Master;


use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Master\TaxGroup;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Utils\RequestAuditHelper;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaxRepo extends ElequentRepository
{
    protected $model;
    public function __construct(Tax $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Tax();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('tax_name', 'like', '%' .$search. '%');
            $query->orWhere('tax_description', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->with(['taxgroup','taxgroup.tax','purchasecoa','salescoa'])->orderBy('tax_name','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Tax();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('tax_name', 'like', '%' .$search. '%');
            $query->orWhere('tax_description', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('tax_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $userId = $request->user_id;
        $arrData = array(
            'tax_name' => $request->tax_name,
            'tax_sign' => $request->tax_sign,
            'tax_type' => $request->tax_type,
            'tax_description' => (!empty($request->tax_description) ? $request->tax_description : ''),
            'tax_periode' => !empty($request->tax_periode) ? $request->tax_periode : '',
            'tax_percentage' => !empty($request->tax_percentage) ? $request->tax_percentage : '0',
            'is_dpp_nilai_Lain' => !empty($request->is_dpp_nilai_Lain) ? $request->is_dpp_nilai_Lain : '0',
            'purchase_coa_id' => !empty($request->purchase_coa_id) ? $request->purchase_coa_id : '0',
            'sales_coa_id' => !empty($request->sales_coa_id) ? $request->sales_coa_id : '0',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId
        );
        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id, [], ['taxgroup'])?->toArray();
        }
        DB::beginTransaction();
        try {
            if(empty($id)) {
                $arrData['created_by'] = $userId;
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $res = $this->create($arrData);
                $action = 'Tambah data master pajak dengan nama '.$request->tax_name;
            } else {
                $res = $this->update($arrData, $id);
                $action = 'Edit data master pajak dengan nama '.$request->tax_name;
            }
            if($res){
                if(!empty($id)){
                    $this->deleteTaxGroup($id);
                    $idTax = $id;
                } else {
                    $idTax = $res->id;
                }
                if($request->tax_type == VarType::TAX_TYPE_GROUP){
                    if(!empty($request->taxgroup)){
                        $taxGroup = json_decode(json_encode($request->taxgroup));
                        foreach ($taxGroup as $key => $item){
                            $arrTaxGroup = array(
                                'tax_id' => $item->tax_id,
                                'id_tax' => $idTax,
                                'majemuk' => $item->majemuk
                            );
                            TaxGroup::create($arrTaxGroup);
                        }
                    }
                }
                ActivityLogService::insertLog([
                    'user_id'         => $userId,
                    'action'          => $action,
                    'model_type'      => Tax::class,
                    'model_id'        => $idTax,
                    'old_values'      => $oldData,
                    'new_values'      => $this->findOne($idTax, [], ['taxgroup'])?->toArray(),
                    'request_payload' => RequestAuditHelper::sanitize($request),
                    'ip_address'      => $request->ip(),
                    'user_agent'      => $request->userAgent(),
                ]);
                DB::commit();
                return true;
            }
            else{
                return false;
            }
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }

    }

    public function deleteTaxGroup($idTax){
        $res = TaxGroup::where(array('id_tax' => $idTax))->delete();
        return $res;
    }

    public static function getTaxId($taxPercentage)
    {
        $tax = Tax::where('tax_percentage', $taxPercentage)->first();
        return $tax ? $tax->id : 0;
    }
}
