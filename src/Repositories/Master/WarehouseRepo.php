<?php
namespace App\Repositories\Master;

use App\Models\Tenant\Master\Warehouse;
use App\Repositories\ElequentRepository;
use App\Utils\Utility;
use Illuminate\Http\Request;

class WarehouseRepo extends ElequentRepository
{
    protected $model;

    public function __construct(Warehouse $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Warehouse();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('warehouse_name', 'like', '%' .$search. '%');
            $query->orWhere('warehouse_code', 'like', '%' .$search. '%');
            $query->orWhere('warehouse_address', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('warehouse_name','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Warehouse();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('warehouse_name', 'like', '%' .$search. '%');
            $query->orWhere('warehouse_code', 'like', '%' .$search. '%');
            $query->orWhere('warehouse_address', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('warehouse_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $whCode = $request->warehouse_code;
        if(empty($whCode)) {
            $whCode = Utility::generateRandomString(5);
        }
        $arrData = array(
            'warehouse_name' => $request->warehouse_name,
            'warehouse_address' => (!empty($request->warehouse_address) ? $request->warehouse_address : ''),
            'warehouse_code' => $whCode,
            'warehouse_meta_field' => '',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $request->user_id
        );
        if(empty($id)) {
            $arrData['created_by'] = $request->user_id;
            $arrData['created_at'] = date('Y-m-d H:i:s');
            return $this->create($arrData);
        } else {
            return $this->update($arrData, $id);
        }
    }

    public static function getWarehouseId($warehouseCode)
    {
        $warehouse = Warehouse::where('warehouse_code', $warehouseCode)->first();
        return $warehouse ? $warehouse->id : 0;
    }
}
