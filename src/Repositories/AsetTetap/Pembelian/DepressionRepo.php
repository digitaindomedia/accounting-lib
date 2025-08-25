<?php

namespace Icso\Accounting\Repositories\AsetTetap\Pembelian;

use App\Models\Tenant\AsetTetap\Pembelian\Depression;
use DateInterval;
use DatePeriod;
use DateTime;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;

class DepressionRepo extends ElequentRepository
{
    protected $model;

    public function __construct(Depression $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function insertData($order, $receiveId,$penyusutanDate,$userId)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $nilaiResidu = $order->nilai_residu;
        $hargaBeli = $order->harga_beli;
        $masa = $order->masa_manfaat;
        $pilihanNilai = $order->pilihan_nilai;
        $nilaiPenyusutan = $order->nilai_penyusutan;
        $now = date("Y-m-d");
        $start    = (new DateTime($penyusutanDate))->modify('first day of this month');
        $end      = (new DateTime($now))->modify('first day of next month');
        $interval = DateInterval::createFromDateString('1 year');
        $intervalMonth = DateInterval::createFromDateString('1 month');
        $periodMonth   = new DatePeriod($start, $intervalMonth, $end);
        $i=0;
        $start_y    = $penyusutanDate;
        $end_y      = $now;
        $getRangeYear   = range(gmdate('Y', strtotime($start_y)), gmdate('Y', strtotime($end_y)));
        $arrHitung = array();
        $totalBulan = $masa;
        if($pilihanNilai == 'masa') {
            $totalBulan = $masa * 12;
        } else if($pilihanNilai == 'masa_bulan') {
            $totalBulan = $masa;
        }
        else {
            $tahun = 100 / $nilaiPenyusutan;
            $totalBulan = $tahun * 12;
        }
        $masaAktif  = date('Y-m-d', strtotime("+$totalBulan months", strtotime($penyusutanDate)));
        foreach ($getRangeYear as $dt) {
            $thn = $dt;
            $jumlahPakai = 12;
            if ($i == 0) {
                $jumlahPakai = $this->getLeftNumberOfMonth($penyusutanDate);
            }
            $hitungSusut = 0;
            if ($masa != '0') {
                if($order->is_saldo_awal == '1'){
                    if ($pilihanNilai == 'masa' || $pilihanNilai == 'masa_bulan') {
                        $hitungSusut = ($hargaBeli - ($nilaiResidu + $order->nilai_akum_penyusutan)) / $totalBulan;
                    }
                }
                else {
                    if ($pilihanNilai == 'masa' || $pilihanNilai == 'masa_bulan') {
                        $hit = ($hargaBeli - $nilaiResidu) / $masa;
                        $hitungSusut = ($jumlahPakai / 12) * $hit;
                    } else {
                        //$hit = ($harga_beli - $nilai_residu) / $masa;
                        $hitungSusut = ($hargaBeli - $nilaiResidu) / $masa;
                    }
                    $hitungSusut = $hitungSusut / $jumlahPakai;
                }

            }
            if ($nilaiPenyusutan != '0') {
                $persen = $nilaiPenyusutan / 100;
                $hit = ($hargaBeli - $nilaiResidu) * $persen;
                $hitungSusut = ($jumlahPakai / 12) * $hit;
                $hitungSusut = $hitungSusut / $jumlahPakai;
            }

            $arrHitung[] = array(
                'tahun' => $thn,
                'penyusutan' => $hitungSusut
            );
            $i = $i + 1;
        }
        foreach ($periodMonth as $dtMonth) {

            $tglNow = $dtMonth->format("Y-m" . "-02");
            $tgl = $this->lastDateMonth($tglNow);
            $thn = $dtMonth->format("Y");
            if ($tgl < $masaAktif) {
                foreach ($arrHitung as $hit) {
                    if ($nilaiResidu != '0') {
                        $totalDepresiasi = $this->getTotalByReceiveId($receiveId);
                        $nilaiBuku = $hargaBeli - $totalDepresiasi;
                        if ($nilaiResidu < $nilaiBuku) {
                            break;
                        }
                    }
                    if ($hit['tahun'] == $thn) {
                        $noJurnal = self::generateCodeTransaction(new Adjustment(), KeyNomor::NO_DEPRESIASI_ASET_TETAP, 'jurnal_no', 'depression_date');
                        $isInserted = Depression::where(array('receive_id' => $receiveId, 'depression_date' => $tgl));
                        if ($isInserted->count() == 0) {
                            $arrDataDepresiasi = array(
                                'receive_id' => $receiveId,
                                'depression_date' => $tgl,
                                'note' => "Depresiasi Aset Tetap pada " .   Utility::convert_tanggal($tgl),
                                'jurnal_no' => $noJurnal,
                                'debet' => 0,
                                'kredit' => $hit['penyusutan']
                            );
                            $resDepresiasi = $this->create($arrDataDepresiasi);
                            $coaIdDebet = $order->penyusutan_coa_id;
                            $coaIdKredit = $order->akumulasi_penyusutan_coa_id;
                            $arrJurnalDebet = array(
                                'transaction_date' => $tgl,
                                'transaction_datetime' => $tgl." ".date('H:i:s'),
                                'created_by' => $userId,
                                'updated_by' => $userId,
                                'transaction_code' => TransactionsCode::DEPRESIASI_ASET_TETAP,
                                'coa_id' => $coaIdDebet,
                                'transaction_id' => $receiveId,
                                'transaction_sub_id' => $resDepresiasi->id,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                                'transaction_no' => $noJurnal,
                                'transaction_status' => JurnalStatusEnum::OK,
                                'debet' => $hit['penyusutan'],
                                'kredit' => 0,
                                'note' => "Depresiasi Aset Tetap pada " .   Utility::convert_tanggal($tgl),
                            );
                            $jurnalTransaksiRepo->create($arrJurnalDebet);
                            $arrJurnalKredit = array(
                                'transaction_date' => $tgl,
                                'transaction_datetime' => $tgl." ".date('H:i:s'),
                                'created_by' => $userId,
                                'updated_by' => $userId,
                                'transaction_code' => TransactionsCode::DEPRESIASI_ASET_TETAP,
                                'coa_id' => $coaIdKredit,
                                'transaction_id' => $receiveId,
                                'transaction_sub_id' => $resDepresiasi->id,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                                'transaction_no' => $noJurnal,
                                'transaction_status' => JurnalStatusEnum::OK,
                                'debet' => 0,
                                'kredit' => $hit['penyusutan'],
                                'note' => "Depresiasi Aset Tetap pada " .   Utility::convert_tanggal($tgl),
                            );
                            $jurnalTransaksiRepo->create($arrJurnalKredit);
                        } else {
                            foreach ($isInserted->get() as $itemAset) {
                                $isJurnal = JurnalTransaksi::where(array('transaction_no' => $noJurnal, 'transaction_code' => TransactionsCode::DEPRESIASI_ASET_TETAP))->count();
                                if ($isJurnal == 0) {
                                    $coaIdDebet = $order->penyusutan_coa_id;
                                    $coaIdKredit = $order->akumulasi_penyusutan_coa_id;
                                    $arrJurnalDebet = array(
                                        'transaction_date' => $tgl,
                                        'transaction_datetime' => $tgl." ".date('H:i:s'),
                                        'created_by' => $userId,
                                        'updated_by' => $userId,
                                        'transaction_code' => TransactionsCode::DEPRESIASI_ASET_TETAP,
                                        'coa_id' => $coaIdDebet,
                                        'transaction_id' => $receiveId,
                                        'transaction_sub_id' => $itemAset->id,
                                        'created_at' => date("Y-m-d H:i:s"),
                                        'updated_at' => date("Y-m-d H:i:s"),
                                        'transaction_no' => $itemAset->jurnal_no,
                                        'transaction_status' => JurnalStatusEnum::OK,
                                        'debet' => $hit['penyusutan'],
                                        'kredit' => 0,
                                        'note' => "Depresiasi Aset Tetap pada " .   Utility::convert_tanggal($tgl),
                                    );
                                    $jurnalTransaksiRepo->create($arrJurnalDebet);
                                    $arrJurnalKredit = array(
                                        'transaction_date' => $tgl,
                                        'transaction_datetime' => $tgl." ".date('H:i:s'),
                                        'created_by' => $userId,
                                        'updated_by' => $userId,
                                        'transaction_code' => TransactionsCode::DEPRESIASI_ASET_TETAP,
                                        'coa_id' => $coaIdKredit,
                                        'transaction_id' => $receiveId,
                                        'transaction_sub_id' => $itemAset->id,
                                        'created_at' => date("Y-m-d H:i:s"),
                                        'updated_at' => date("Y-m-d H:i:s"),
                                        'transaction_no' => $itemAset->jurnal_no,
                                        'transaction_status' => JurnalStatusEnum::OK,
                                        'debet' => 0,
                                        'kredit' => $hit['penyusutan'],
                                        'note' => "Depresiasi Aset Tetap pada " .   Utility::convert_tanggal($tgl),
                                    );
                                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                                }
                            }
                        }
                    }

                }
            }
        }
    }

    public function getTotalByReceiveId($receiveId)
    {
        $total = Depression::where('receive_id', $receiveId)->sum('kredit');
        return $total;
    }

    public function getLeftNumberOfMonth($d)
    {
        $todaysmonth = date('n', strtotime($d));
        $jum = 12 - $todaysmonth;
        $jum = $jum + 1;
        return $jum;
    }

    function lastDateMonth($now_date) {
        $last_date = date("Y-m-t", strtotime($now_date));
        return $last_date;
    }

    public static function totalDepresiasiByPenerimaanId($idRec,$startDate='', $endDate='')
    {
        $find = Depression::where('receive_id', $idRec);
        if(!empty($startDate) && !empty($endDate)){
            $find = $find->whereBetween('depression_date', array($startDate,$endDate));
        }
        $total = $find->sum('kredit');
        return $total;
    }

    public static function totalDepresiasiByAsetId($idAset,$startDate='', $endDate='')
    {
        $find = Depression::join('als_aset_tetap_receive', 'als_aset_tetap_depression.receive_id', '=', 'als_aset_tetap_receive.id')->where('als_aset_tetap_receive.order_id', $idAset);
        if(!empty($startDate) && !empty($endDate)){
            $find = $find->whereBetween('als_aset_tetap_depression.depression_date', array($startDate,$endDate));
        }
        $total = $find->sum('als_aset_tetap_depression.kredit');
        return $total;
    }
}
