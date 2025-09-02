<?php
namespace Icso\Accounting\Repositories\Master\Coa;

use DateTime;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\Constants;
use Illuminate\Http\Request;

class CoaRepo extends ElequentRepository
{

    protected $model;

    public function __construct(Coa $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Coa();
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })
        ->when(!empty($search), function ($query) use($search){
            $query->where('coa_name', 'like', '%' .$search. '%');
            $query->orWhere('coa_code', 'like', '%' .$search. '%');
        })->orderBy('coa_code','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Coa();
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })
        ->when(!empty($search), function ($query) use($search){
            $query->where('coa_name', 'like', '%' .$search. '%');
            $query->orWhere('coa_code', 'like', '%' .$search. '%');
        })->orderBy('coa_code','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $dokumen = "";
        $headcoa = $request->head_coa;
        $subheadcoa = $request->subhead_coa;
        $subheadcoa2 = $request->subhead_coa2;
        $parentId = '0';
        $level = '0';
        if(empty($headcoa))
        {
            $parentId = '0';
            $level = '1';
        }
        else
        {
            $parentId = $headcoa;
            if(empty($subheadcoa))
            {
                $level = '2';
                $parentId = $headcoa;
            }
            else
            {
                $parentId = $subheadcoa;
                if (empty($subheadcoa2)) {
                    $parentId = $subheadcoa;
                    $level = '3';
                } else {
                    $parentId = $subheadcoa2;
                    $level = '4';
                }
            }
        }

        $coa_code = $request->coa_code;
        if(empty($coa_code))
        {
            $coa_code = '';
        }
        $coa_position = $request->coa_position;
        if(empty($coa_position))
        {
            $coa_position = '';
        }
        $connect_db = $request->connect_db;
        if(empty($connect_db))
        {
            $connect_db = '0';
        }
        $neraca = $request->neraca;
        $laba_rugi = $request->laba_rugi;

        if($parentId != '0')
        {
            $par = $this->getParentRoot($parentId);
            if(!empty($par))
            {
                $neraca = $par->neraca;
                $laba_rugi = $par->laba_rugi;
                if(empty($coa_position)){
                    $coa_position = $par->coa_position;
                }
            }
        }
        if($level == 4 && empty($coa_code)) {
            return false;
        } else
        {
            $arrData = array(
                'coa_name' => $request->coa_name,
                'coa_code' => $coa_code,
                'description' => '',
                'coa_position' => $coa_position,
                'coa_parent' => !empty($parentId) ? $parentId : 0,
                'coa_level' => !empty($level) ? $level : 0,
                'neraca' => $neraca,
                'laba_rugi' => $laba_rugi,
                'coa_category' => (!empty($request->coa_category) ? $request->coa_category : ''),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $request->user_id,
                'field_name' => (!empty($request->field_name) ? $request->field_name : ''),
                'connect_db' => !empty($connect_db) ? $connect_db : 0,
            );
            if (empty($id)) {
                $arrData['coa_status'] = Constants::AKTIF;
                $arrData['created_by'] = $request->user_id;
                $arrData['created_at'] = date('Y-m-d H:i:s');
                return $this->create($arrData);
            } else {
                return $this->update($arrData, $id);
            }
        }

    }

    public function getAllDataByOpt($search, $page, $perpage, $level = '', $cat = '', array $where = [])
    {
        // TODO: Implement getAllDataByOpt() method.
        $model = new Coa();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('coa_name', 'like', '%' .$search. '%');
            $query->orWhere('coa_code', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->when(!empty($level), function ($query) use($level){
                $query->where('coa_level','=',$level);
        })->when(!empty($cat), function ($query) use($cat){
            $query->where('coa_category','=',$cat);
        })->orderBy('coa_code','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataByOpt($search, $level = '', $cat = '', array $where = [])
    {
        // TODO: Implement getAllTotalDataByOpt() method.
        $model = new Coa();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('coa_name', 'like', '%' .$search. '%');
            $query->orWhere('coa_code', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->when(!empty($level), function ($query) use($level){
            $query->where('coa_level','=',$level);
        })->when(!empty($cat), function ($query) use($cat){
            $query->where('coa_category','=',$cat);
        })->orderBy('coa_code','asc')->count();
        return $dataSet;
    }

    public function getParentRoot($id)
    {
        // TODO: Implement getParentRoot() method.
        $res = $this->findAllByWhere(array('id' => $id), array());
        foreach ($res as $r)
        {
            if($r->coa_parent == '0')
            {
                return $r;
            }
            else
            {
                return $this->getParentRoot($r->coa_parent);
            }
        }
    }

    public function getChildRoot(&$list, $parent)
    {
        // TODO: Implement getChildRoot() method.
        $tree = array();
        foreach ($parent as $k=>$l){
            if(isset($list[$l['id']])){
                $l['children'] = $this->getChildRoot($list, $list[$l['id']]);
            }
            $tree[] = $l;
        }
        return $tree;
    }

    public function updateDate(Request $request)
    {
        // TODO: Implement updateDate() method.
        $arrData = array();
        if ($request->coa_level == '1') {
            $neraca = $request->neraca;
            $laba_rugi = $request->laba_rugi;
            $neracaType = $request->neraca_type;
            $arrData = array(
                'coa_name' => $request->coa_name,
                'coa_position' => $request->coa_position,
                'neraca' => $neraca,
                'laba_rugi' => $laba_rugi,
                'neraca_type' => $neracaType,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $request->user_id
            );
        } else if ($request->coa_level == '2' || $request->coa_level == '3') {
            $arrData = array(
                'coa_name' => $request->coa_name,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $request->user_id
            );
            if(!empty($request->laba_rugi_type)){
                $arrData['laba_rugi_type'] = $request->laba_rugi_type;
            }
        } else {
            $arrData = array(
                'coa_name' => $request->coa_name,
                'coa_code' => $request->coa_code,
                'coa_category' => (!empty($request->coa_category) ? $request->coa_category : ''),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $request->user_id,
                'field_name' => (!empty($request->field_name) ? $request->field_name : ''),
                'connect_db' => $request->connect_db
            );
        }
        $res = $this->update($arrData, $request->id);
        return $res;
    }

    public function getChild($id, $fromDate, $untilDate,&$saldoTotal)
    {
        // TODO: Implement getParentRoot() method.
        $res = $this->findAllByWhere(array('coa_parent' => $id), array());
        $saldo = 0;
        foreach ($res as $item)
        {
            if($item->coa_level == '4')
            {
                $saldoCoaItem = JurnalTransaksiRepo::sumSaldoAwal($item->id,$fromDate,$untilDate,'between');
                $item->saldo = $saldoCoaItem;
                $saldoTotal = $saldoTotal + $saldoCoaItem;
            }
            else
            {
                return $this->getChild($item->id,$fromDate,$untilDate);
            }
        }
    }

    public function totalSaldoCoaLevel4($id,$fromDate,$untilDate){
        $res = $this->findAllByWhere(array('coa_parent' => $id), array());
        $saldo = 0;
        foreach ($res as $item)
        {
            $saldoCoaItem = JurnalTransaksiRepo::sumSaldoAwal($item->id,$fromDate,$untilDate,'between');
            if($item->coa_category == 'saldo_laba'){
                $extr = explode("-",$untilDate);
                $date = $extr[0]."-12-31";
                $newdate = date("Y-m-d",strtotime ( '-1 year' , strtotime ( $date ) )) ;
                $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($newdate,"");
                $saldoCoaItem = $saldoCoaItem + $saldoCoaItemSaldoLaba['ebt'];
            }
            else if($item->coa_category == 'saldo_laba_tahun_berjalan'){
                $extr = explode("-",$untilDate);
                $dariTanggal = $extr[0]."-01-01";
                $d = new DateTime($untilDate, new \DateTimeZone('UTC'));
                $d->modify('first day of previous month');
                $year = $d->format('Y'); //2012
                $month = $d->format('m'); //12
                $sampaiTanggal = $year."-".$month."-31";
                $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($dariTanggal,$sampaiTanggal);
                $saldoCoaItem = $saldoCoaItem + $saldoCoaItemSaldoLaba['ebt'];
            }
            else if($item->coa_category == 'saldo_laba_bulan_berjalan'){
                $extr = explode("-",$untilDate);
                $driTanggal = $extr[0]."-".$extr[1]."-01";
                $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($driTanggal,$untilDate);
                $saldoCoaItem = $saldoCoaItem + $saldoCoaItemSaldoLaba['ebt'];
            }
            $saldo = $saldo + $saldoCoaItem;
        }
        return $saldo;
    }

    public function totalSaldoCoaLevel3($id,$fromDate,$untilDate){
        $res = $this->findAllByWhere(array('coa_parent' => $id), array());
        $saldo = 0;
        foreach ($res as $item)
        {
            $saldoLevel4 = $this->totalSaldoCoaLevel4($item->id,$fromDate,$untilDate);
            $saldo = $saldoLevel4 + $saldo;
        }
        return $saldo;
    }

    public function totalSaldoCoaLevel2($id,$fromDate,$untilDate){
        $res = $this->findAllByWhere(array('coa_parent' => $id), array());
        $saldo = 0;
        foreach ($res as $item)
        {
            $saldoLevel3 = $this->totalSaldoCoaLevel3($item->id,$fromDate,$untilDate);
            $saldo = $saldoLevel3 + $saldo;
        }
        return $saldo;
    }

    public static function getCoaId($coaCode)
    {
        $coa = Coa::where('coa_code', $coaCode)->first();
        return $coa ? $coa->id : 0;
    }

    public static function getCoaById($coaId)
    {
        $coa = Coa::where('id', $coaId)->first();
        return $coa;
    }
}
