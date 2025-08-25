<?php

namespace Icso\Accounting\Repositories\Akuntansi\Jurnal;


use Icso\Accounting\Models\Akuntansi\JurnalAkun;
use Icso\Accounting\Repositories\ElequentRepository;

class JurnalAkunRepo extends ElequentRepository
{

    protected $model;

    public function __construct(JurnalAkun $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new JurnalAkun();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('data_sess', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('jurnal_id','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new JurnalAkun();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('data_sess', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('jurnal_id','asc')->get();
        return $dataSet;
    }
}
