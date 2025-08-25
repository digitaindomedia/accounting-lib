<?php
namespace Icso\Accounting\Repositories\Master;

use App\Models\Tenant\Master\Unit;
use App\Repositories\BaseRepo;
use App\Repositories\ElequentRepository;
use Illuminate\Http\Request;

class UnitRepo extends ElequentRepository
{

    protected $model;

    public function __construct(Unit $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }


    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Unit();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('unit_name', 'like', '%' .$search. '%');
            $query->orWhere('unit_code', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('unit_name','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Unit();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('unit_name', 'like', '%' .$search. '%');
            $query->orWhere('unit_code', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('unit_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $arrData = array(
            'unit_name' => $request->unit_name,
            'unit_code' => $request->unit_code,
            'unit_description' => !empty($request->unit_description) ? $request->unit_description: '',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $request->user_id
        );
        if(empty($id)) {
            $arrData['created_at'] = date('Y-m-d H:i:s');
            $arrData['created_by'] = $request->user_id;
            return $this->create($arrData);
        } else {
            return $this->update($arrData,$id);
        }
    }
}
