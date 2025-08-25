<?php
namespace Icso\Accounting\Repositories\Akuntansi;

use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\ElequentRepository;
use Illuminate\Http\Request;

class JurnalTransaksiRepo extends ElequentRepository
{

    protected $model;

    public function __construct(JurnalTransaksi $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new JurnalTransaksi();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('transaction_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('transaction_date','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new JurnalTransaksi();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('transaction_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('transaction_date','asc')->get();
        return $dataSet;
    }

    public function getAllDataWithDateBy($search, $page, $perpage, array $where = [],array $orderby = [], $with = [], $whereBetween=[])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new JurnalTransaksi();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('transaction_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })
        ->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween($whereBetween['column'], $whereBetween['range']);
        })
        ->when(!empty($orderby), function ($query) use($orderby){
            $pairs = array_chunk($orderby, 2);
            foreach ($pairs as $pair) {
                $query->orderBy(...$pair);
            }
        })
        ->when(!empty($with), function ($query) use($with){$query->with($with);})->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataWithDateBy($search, array $where = [],array $orderby = [], $with = [], $whereBetween=[])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new JurnalTransaksi();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('transaction_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })
        ->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween($whereBetween['column'], $whereBetween['range']);
        })
        ->when(!empty($orderby), function ($query) use($orderby){
            $pairs = array_chunk($orderby, 2);
            foreach ($pairs as $pair) {
                $query->orderBy(...$pair);
            }
        })
        ->when(!empty($with), function ($query) use($with){$query->with($with);})->count();
        return $dataSet;
    }

    public static function sumSaldoAwal($coa_id, $dari,$sampai='', $sign='<'){
        if($sign == 'between') {
            $totalDebet = JurnalTransaksi::where([['coa_id', '=', $coa_id]])->whereBetween('transaction_date',[$dari,$sampai])->sum('debet');
            $totalKredit = JurnalTransaksi::where([['coa_id', '=', $coa_id]])->whereBetween('transaction_date',[$dari,$sampai])->sum('kredit');
        } else{
            $totalDebet = JurnalTransaksi::where([['transaction_datetime', $sign, $dari], ['coa_id', '=', $coa_id]])->sum('debet');
            $totalKredit = JurnalTransaksi::where([['transaction_datetime', $sign, $dari], ['coa_id', '=', $coa_id]])->sum('kredit');
        }
        $findCoa = Coa::where(array('id' => $coa_id))->first();
        $total = 0;
        if(!empty($findCoa)){
            if($findCoa->coa_position == 'debet'){
                $total = $totalDebet - $totalKredit;
            } else {
                $total = $totalKredit - $totalDebet;
            }
        }
        return $total;
    }

    public static function sumSaldoJurnal($coa_id, $dari,$sampai='', $sign='between', $position = 'kredit'){
        if($sign == 'between') {
            $totalDebet = JurnalTransaksi::where([['coa_id', '=', $coa_id]])->whereBetween('transaction_date',[$dari,$sampai])->sum('debet');
            $totalKredit = JurnalTransaksi::where([['coa_id', '=', $coa_id]])->whereBetween('transaction_date',[$dari,$sampai])->sum('kredit');
        } else{
            $totalDebet = JurnalTransaksi::where([['transaction_datetime', $sign, $dari], ['coa_id', '=', $coa_id]])->sum('debet');
            $totalKredit = JurnalTransaksi::where([['transaction_datetime', $sign, $dari], ['coa_id', '=', $coa_id]])->sum('kredit');
        }
        $total = 0;
        if($position == 'debet'){
            $total = $totalDebet - $totalKredit;
        } else {
            $total = $totalKredit - $totalDebet;
        }
        return $total;
    }

    public static function filterArrayByCoaId($data,$key){
        $arraData = array();
        if(count($data) > 0){
            $index = 0;
            $saldoAwal = 0;
            foreach ($data as $item){
                if($item->coa_id == $key){
                    if($index == 0){
                        $saldoAwal = self::sumSaldoAwal($key, $item->transaction_datetime);
                    }
                    $total = 0;
                    $findCoa = Coa::where(array('id' => $key))->first();
                    if(!empty($findCoa)){
                        if($findCoa->coa_position == 'debet'){
                            $total = $item->debet - $item->kredit;
                        } else {
                            $total = $item->kredit - $item->debet;
                        }
                    }

                    $saldoAwal =$saldoAwal + $total;
                    $item->saldo = $saldoAwal;
                    $arraData[] = $item;
                    $index = $index + 1;
                }
            }
        }
        return $arraData;
    }

    public static function labaRugi($fromDate, $untilDate)
    {
        $arrCoaLabaRugiPendapatan = Coa::where(array('laba_rugi' => TypeEnum::IS_LABA_RUGI, 'laba_rugi_type' => TypeEnum::LABA_RUGI_TYPE_PENDAPATAN))->get();
        $arrCoaLabaRugiBiayaOperasional = Coa::where(array('laba_rugi' => TypeEnum::IS_LABA_RUGI, 'laba_rugi_type' => TypeEnum::LABA_RUGI_TYPE_BIAYA_OPERASIONAL))->get();
        $arrCoaLabaRugiBiayaOther = Coa::where(array('laba_rugi' => TypeEnum::IS_LABA_RUGI, 'laba_rugi_type' => TypeEnum::LABA_RUGI_TYPE_BIAYA_OTHER))->get();

        $labaRugiPendapatan = self::arrDataLabaRugi($arrCoaLabaRugiPendapatan, $fromDate, $untilDate, 'Pendapatan');
        $labaRugiBiayaOperasional = self::arrDataLabaRugi($arrCoaLabaRugiBiayaOperasional, $fromDate, $untilDate, 'Biaya Operasional');
        $labaRugiBiayaOther= self::arrDataLabaRugi($arrCoaLabaRugiBiayaOther, $fromDate, $untilDate, 'Biaya Other');

        $ebt = $labaRugiPendapatan['total'] + $labaRugiBiayaOperasional['total'] + $labaRugiBiayaOther['total'];
        return array(
            'pendapatan' => $labaRugiPendapatan,
            'biaya_operasional' => $labaRugiBiayaOperasional,
            'other' => $labaRugiBiayaOther,
            'ebt' => $ebt
        );
    }

    public static function arrDataLabaRugi($arrDataType, $fromDate, $untilDate, $fieldType){
        $arrData = array();
        $grandTotal = 0;
        if(count($arrDataType) > 0){
            foreach ($arrDataType as $item){
                $arrData[] = array(
                    'coa_code' => "",
                    'coa_name' => $item->coa_name
                );
                $subSaldo = 0;
                $getAllChild = Coa::where(array('coa_parent' => $item->id))->get();
                if(count($getAllChild) > 0){
                    foreach ($getAllChild as $child){
                        $saldo = 0;
                        if(empty($untilDate)){
                            $saldo = self::sumSaldoJurnal($child->id, $fromDate,$untilDate,"<");
                        } else {
                            $saldo = self::sumSaldoJurnal($child->id, $fromDate,$untilDate);
                        }

                        $arrData[] = array(
                            'coa_code' =>$child->coa_code,
                            'coa_name' => $child->coa_name,
                            'saldo' => $saldo
                        );
                        $subSaldo = $subSaldo + $saldo;
                    }
                }
                $arrData[] = array(
                    'coa_code' => "",
                    'coa_name' => "Total ".$item->coa_name,
                    'saldo' => $subSaldo
                );
                $grandTotal = $grandTotal + $subSaldo;
            }
        }
        $arrData[] = array(
            'coa_code' => "",
            'coa_name' => "Total ".$fieldType,
            'saldo' => $grandTotal
        );
        return array(
            'total' => $grandTotal,
            'coa' => $arrData
        );
    }

    public static function deleteJurnalTransaksi($transactionCode,$idTransaction){
        JurnalTransaksi::where(array('transaction_code' => $transactionCode,'transaction_id' => $idTransaction))->delete();
    }
}
