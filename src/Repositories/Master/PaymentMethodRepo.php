<?php
namespace Icso\Accounting\Repositories\Master;


use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Repositories\ElequentRepository;
use Illuminate\Http\Request;

class PaymentMethodRepo extends ElequentRepository
{

    protected $model;

    public function __construct(PaymentMethod $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new PaymentMethod();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('payment_name', 'like', '%' .$search. '%');
            $query->orWhere('descriptions', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('payment_name','asc')->offset($page)->limit($perpage)->get();
        if(count($dataSet) > 0) {
            foreach ($dataSet as $item) {
                $findCoa = Coa::where(array('id' => $item->coa_id))->first();
                $item->coa = $findCoa;
            }
        }
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new PaymentMethod();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('payment_name', 'like', '%' .$search. '%');
            $query->orWhere('descriptions', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('payment_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $coa_id = $request->coa_id;

        $arrData = array(
            'payment_name' => $request->payment_name,
            'descriptions' => (!empty($request->descriptions) ? $request->descriptions : ''),
            'coa_id' => $coa_id,
            'updated_by' => $request->user_id,
            'updated_at' => date('Y-m-d H:i:s')
        );
        if(empty($id)) {
            $arrData['created_by'] = $request->user_id;
            $arrData['created_at'] = date('Y-m-d H:i:s');
            return $this->create($arrData);
        } else {
            return $this->update($arrData, $id);
        }
    }
}
