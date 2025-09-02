<?php

namespace Icso\Accounting\Http\Controllers\Akuntansi;

use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Exports\NeracaListExport;
use Icso\Accounting\Exports\NeracaTExport;
use Icso\Accounting\Models\Akuntansi\SaldoAwal;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Icso\Accounting\Utils\Constants;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTime;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class NeracaController extends Controller
{
    protected $jurnalTransaksiRepo;
    protected $coaRepo;

    public function __construct(JurnalTransaksiRepo $jurnalTransaksiRepo, CoaRepo $coaRepo)
    {
        $this->jurnalTransaksiRepo = $jurnalTransaksiRepo;
        $this->coaRepo = $coaRepo;
    }

    public function show(Request $request){
        $findPeriode = SaldoAwal::where(array('is_default' => Constants::AKTIF))->first();
        $fromDate = date('Y-m-d');
        if(!empty($findPeriode)){
            $fromDate = $findPeriode->saldo_date;
        }
        $untilDate =  $request->filter_date;
        $periode =  $request->periode;
        $waktu =  $request->waktu;
        $res = $this->coaRepo->findAllByWhere(array('neraca' => TypeEnum::IS_NERACA),array());
        $totalAktiva = array();
        $totalPassiva = array();
        if(count($res) > 0){
            $arr = array();
            foreach ($res as $r)
            {
                $arr[] = $r;
            }
            $res_child = array();
            $totalLiabilitas = array();
            $totalEkuitas = array();

            foreach ($arr as $a)
            {
                $saldo = array();
                $aktiva = array();
                $tanggalNeraca = array();
                if($a['coa_level'] == '4'){
                    $saldoCoaItem = JurnalTransaksiRepo::sumSaldoAwal($a['id'],$fromDate,$untilDate,'between');

                    if($a['coa_category'] == 'saldo_laba'){
                        $extr = explode("-",$untilDate);
                        $date = $extr[0]."-12-31";
                        $newdate = date("Y-m-d",strtotime ( '-1 year' , strtotime ( $date ) )) ;
                        $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($newdate,"");
                        $saldoCoaItem = $saldoCoaItem + $saldoCoaItemSaldoLaba['ebt'];
                    }
                    else if($a['coa_category'] == 'saldo_laba_tahun_berjalan'){
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
                    else if($a['coa_category'] == 'saldo_laba_bulan_berjalan'){
                        $extr = explode("-",$untilDate);
                        $driTanggal = $extr[0]."-".$extr[1]."-01";
                        $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($driTanggal,$untilDate);
                        $saldoCoaItem = $saldoCoaItem + $saldoCoaItemSaldoLaba['ebt'];
                    }
                    $saldo[] = $saldoCoaItem;

                    if(!empty($periode)){
                        $time = "year";
                        if($waktu == 'bulan'){
                            $time = 'month';
                        }
                        for ($i=1; $i<=$periode;$i++){
                            $newdate = date("Y-m-d",strtotime ( "-$i ".$time , strtotime ( $untilDate ) )) ;
                            $saldoCoaItem = JurnalTransaksiRepo::sumSaldoAwal($a['id'],$fromDate,$newdate,'between');
                            $saldo[] = $saldoCoaItem;
                        }

                    }
                    $a['saldo'] = $saldo;
                }
                else if($a['coa_level'] == '3'){
                    $saldoCoa3 = $this->coaRepo->totalSaldoCoaLevel4($a['id'],$fromDate,$untilDate);
                    $saldo[] = $saldoCoa3;
                    if(!empty($periode)){
                        $time = "year";
                        if($waktu == 'bulan'){
                            $time = 'month';
                        }
                        for ($i=1; $i<=$periode;$i++) {
                            $newdate = date("Y-m-d", strtotime("-$i " . $time, strtotime($untilDate)));
                            $saldoCoaItem = $this->coaRepo->totalSaldoCoaLevel4($a['id'], $fromDate, $newdate);
                            $saldo[] = $saldoCoaItem;
                        }
                    }
                    $a['saldo'] = $saldo;
                    //$a['saldo'] = $saldoCoa3;
                }
                else if($a['coa_level'] == '2'){
                    $saldoCoa2 = $this->coaRepo->totalSaldoCoaLevel3($a['id'],$fromDate,$untilDate);
                    $saldo[] = $saldoCoa2;
                    if(!empty($periode)){
                        $time = "year";
                        if($waktu == 'bulan'){
                            $time = 'month';
                        }
                        for ($i=1; $i<=$periode;$i++) {
                            $newdate = date("Y-m-d", strtotime("-$i " . $time, strtotime($untilDate)));
                            $saldoCoaItem = $this->coaRepo->totalSaldoCoaLevel3($a['id'], $fromDate, $newdate);
                            $saldo[] = $saldoCoaItem;
                        }
                    }
                    $a['saldo'] = $saldo;
                    //$a['saldo'] = $saldoCoa2;
                } else {
                    $saldoCoa = $this->coaRepo->totalSaldoCoaLevel2($a['id'],$fromDate,$untilDate);
                    $saldo[] = $saldoCoa;
                    $aktiva[] = $saldoCoa;
                    if($a['neraca_type'] == 'liabilitas'){
                        $totalLiabilitas[] = $saldoCoa;
                    }
                    if($a['neraca_type'] == 'ekuitas'){
                        $totalEkuitas[] = $saldoCoa;
                    }
                    //$pasiva[] = $totalLiabilitas + $saldoCoa;
                    $tanggalNeraca[] = date("d/m/Y",strtotime ($untilDate));
                    if(!empty($periode)){
                        $time = "year";
                        if($waktu == 'bulan'){
                            $time = 'month';
                        }
                        for ($i=1; $i<=$periode;$i++) {
                            $newdate = date("Y-m-d", strtotime("-$i " . $time, strtotime($untilDate)));
                            $saldoCoaItem = $this->coaRepo->totalSaldoCoaLevel2($a['id'], $fromDate, $newdate);
                            $saldo[] = $saldoCoaItem;
                            $aktiva[] = $saldoCoaItem;
                            if ($a['neraca_type'] == 'liabilitas') {
                                //$totalLiabilitas2 = $saldoCoaItem;
                                $totalLiabilitas[] = $saldoCoaItem;

                            }
                            if($a['neraca_type'] == 'ekuitas'){
                                $totalEkuitas[] = $saldoCoaItem;
                            }
                           // $pasiva[] = $totalLiabilitas2 + $saldoCoaItem;
                            $tanggalNeraca[] = date("d/m/Y", strtotime($newdate));
                        }
                    }
                    $a['saldo'] = $saldo;
                    $a['tanggal_neraca'] = $tanggalNeraca;
                    //$a['saldo'] = $saldoCoa;
                    if($a['neraca_type'] == 'asset'){
                        $a['total_aktiva'] = $aktiva;
                        $totalAktiva[] = $aktiva;
                    }
                    $pasiva = array();
                    if(count($totalEkuitas) == count($totalLiabilitas)){
                        foreach ($totalLiabilitas as $key => $item){
                            $pasiva[] = $item + $totalEkuitas[$key];
                        }
                    }
                    $a['total_passiva'] = $pasiva;
                    if(!empty($pasiva)){
                        $totalPassiva[] = $pasiva;
                    }

                }

                $res_child[$a['coa_parent']][] = $a;
            }

            $res_chi = '';
            if(count($res_child) > 0)
            {
                $res_chi = $this->coaRepo->getChildRoot($res_child,$res_child[0]);
            }
            /*foreach ($data as $item){
                if($item->coa_level == '4'){
                    $saldoCoaItem = JurnalTransaksiRepo::sumSaldoAwal($item->id,$fromDate,$untilDate,'between');
                    $item->saldo = $saldoCoaItem;
                }
                else if($item->coa_level == '3'){
                    $saldoCoa3 = $this->coaRepo->totalSaldoCoaLevel4($item->id,$fromDate,$untilDate);
                    $item->saldo = $saldoCoa3;
                }
                else if($item->coa_level == '2'){
                    $saldoCoa2 = $this->coaRepo->totalSaldoCoaLevel3($item->id,$fromDate,$untilDate);
                    $item->saldo = $saldoCoa2;
                } else {
                    $saldoCoa = $this->coaRepo->totalSaldoCoaLevel2($item->id,$fromDate,$untilDate);
                    $item->saldo = $saldoCoa;
                }
            }*/
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res_chi;
            $this->data['aktiva'] = $totalAktiva;
            $this->data['passiva'] = $totalPassiva;
        }
        else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function export(Request $request)
    {
        if($request->layout == 'grid'){
            return Excel::download(new NeracaTExport($request), 'neraca.xlsx');
        }
        return Excel::download(new NeracaListExport($request), 'neraca.xlsx');
    }

    public function exportToPdf(Request $request)
    {
        $export = new NeracaListExport($request);
        $pdfRes = 'neraca_list_pdf';
        $data = $export->getData();
        $pdf = PDF::loadView($pdfRes, ['data' => $data]);
        if($request->layout == 'grid'){
            $export = new NeracaTExport($request);
            $pdfRes = 'neraca_grid_pdf';
            $data = $export->getData();
            $pdf = PDF::loadView($pdfRes, ['data' => $data])->setPaper('legal', 'landscape');
        }


        return $pdf->download('neraca.pdf');
    }
}
