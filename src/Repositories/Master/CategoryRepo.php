<?php
namespace Icso\Accounting\Repositories\Master;

use App\Models\Tenant\Master\Category;
use App\Repositories\ElequentRepository;
use Illuminate\Http\Request;

class CategoryRepo extends ElequentRepository{

    protected $model;

    public function __construct(Category $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Category();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('category_name', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('category_name','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Category();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('category_name', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('category_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $arr = array(
            'category_name' => $request->category_name,
            'category_description' => (!empty($request->category_description) ? $request->category_description : ''),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $request->user_id
        );
        if(empty($id)) {
            $arr['category_type'] = $request->category_type;
            $arr['created_at'] = date('Y-m-d H:i:s');
            $arr['created_by'] = $request->user_id;
            return $this->create($arr);
        } else {
            return $this->update($arr,$id);
        }
    }
}
