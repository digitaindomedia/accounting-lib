<?php
namespace Icso\Accounting\Repositories\Akuntansi;


use Icso\Accounting\Models\Akuntansi\SaldoAwalAkun;
use Icso\Accounting\Repositories\ElequentRepository;

class SaldoAwalAkunRepo extends ElequentRepository {

    protected $model;

    public function __construct(SaldoAwalAkun $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new SaldoAwalAkun();
        $dataSet = $model->when(!empty($search), function ($query) use($search){

        })->when(!empty($where), function ($query) use($where){

        })->orderBy('id','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new SaldoAwalAkun();
        $dataSet = $model->when(!empty($search), function ($query) use($search){

        })->when(!empty($where), function ($query) use($where){

        })->orderBy('id','asc')->get();
        return $dataSet;
    }
}
