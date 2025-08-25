<?php

namespace Icso\Accounting\Exports;
use App\Enums\TypeEnum;
use App\Models\Tenant\Akuntansi\SaldoAwal;
use App\Models\Tenant\Master\Coa;
use App\Repositories\Tenant\Akuntansi\JurnalTransaksiRepo;
use App\Repositories\Tenant\Master\Coa\CoaRepo;
use App\Repositories\Tenant\Utils\SettingRepo;
use App\Utils\Constants;
use App\Utils\Utility;
use DateTime;
use Hamcrest\Util;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NeracaListExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $coa;
    protected $processedIds;
    protected $request;
    protected $fromDate;
    protected $untilDate;
    protected $periode;
    protected $waktu;
    protected $coaRepo;

    public function __construct(Request $request)
    {
        $this->coa = Coa::where('neraca', TypeEnum::IS_NERACA)->with('children')->get();
        $this->processedIds = [];
        $this->request = $request;
        $findPeriode = SaldoAwal::where(array('is_default' => Constants::AKTIF))->first();
        $this->fromDate = date('Y-m-d');
        if (!empty($findPeriode)) {
            $this->fromDate = $findPeriode->saldo_date;
        }
        $this->untilDate = $request->filter_date;
        $this->periode = $request->periode;
        $this->waktu = $request->waktu;
        $this->coaRepo = new CoaRepo(new Coa());
    }

    public function collection()
    {
        return collect($this->getData());
    }

    public function headings(): array
    {
        return [
            'COA Name',
            'Saldo'
        ];
    }

    public function map($row): array
    {
        return [
            $row['coa_name'],
            $row['saldo']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B')->getAlignment()->setHorizontal('right');
    }

    public function getData()
    {
        $data = [];
        $totalAktiva = 0;
        $totalPassiva = 0;
        foreach ($this->coa as $item) {
            $dataItem = $this->mapItem($item, 0, $item->neraca_type);
            foreach ($dataItem as $td){
                if(!empty($td['type'])){
                    if($td['type'] == 'liabilitas'){
                        if(!empty($td['saldo'])){
                            $totalPassiva = $totalPassiva + Utility::remove_commas($td['saldo']);
                        }

                    }
                    if($td['type'] == 'ekuitas'){
                        if(!empty($td['saldo'])){
                            $totalPassiva = $totalPassiva + Utility::remove_commas($td['saldo']);
                        }

                    }
                }
            }
            $data = array_merge($data, $dataItem);

        }
        $data[] = array_merge(
            $data, [
            'coa_name' => "Total Passiva",
            'saldo' => number_format($totalPassiva, SettingRepo::getSeparatorFormat()),
            ]
        );
        return $data;
    }

    protected function mapItem($item, $level, $type='')
    {
        if (in_array($item->id, $this->processedIds)) {
            return [];
        }

        $this->processedIds[] = $item->id;
        $data = [];

        $prefix = str_repeat(' ', $level * 4);
        $coaCodeName = $item->coa_name;
        if ($item->coa_level == 4) {
            $coaCodeName .= "(" . $item->coa_code . ")";
        }
        $data[] = [
            'coa_name' => $prefix . $coaCodeName,
            'saldo' => $this->getSaldo($item, $item->coa_level),
            'level' => $level,
            'type' => $type
        ];

        foreach ($item->children as $child) {
            $childData = $this->mapItem($child, $level + 1);
            $data = array_merge($data, $childData);
        }

        if (!empty($item->children) && $item->coa_level != 4) {
            $saldoCoa = 0;
            if ($item->coa_level == 3) {
                $saldoCoa = $this->coaRepo->totalSaldoCoaLevel4($item->id, $this->fromDate, $this->untilDate);
            } else if ($item->coa_level == 2) {
                $saldoCoa = $this->coaRepo->totalSaldoCoaLevel3($item->id, $this->fromDate, $this->untilDate);
            } else {
                $saldoCoa = $this->coaRepo->totalSaldoCoaLevel2($item->id, $this->fromDate, $this->untilDate);
            }
            $data[] = [
                'coa_name' => $prefix . 'Total ' . $item->coa_name,
                'saldo' => number_format($saldoCoa, SettingRepo::getSeparatorFormat()),
                'level' => $level,
                'type' => $item->neraca_type
            ];
        }

        return $data;
    }

    private function getSaldo($it, $coaLevel)
    {
        if ($coaLevel == 4) {
            $saldoCoaItem = JurnalTransaksiRepo::sumSaldoAwal($it->id, $this->fromDate, $this->untilDate, 'between');
            if ($it->coa_category == 'saldo_laba') {
                $extr = explode("-", $this->untilDate);
                $date = $extr[0] . "-12-31";
                $newdate = date("Y-m-d", strtotime('-1 year', strtotime($date)));
                $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($newdate, "");
                $saldoCoaItem += $saldoCoaItemSaldoLaba['ebt'];
            } else if ($it->coa_category == 'saldo_laba_tahun_berjalan') {
                $extr = explode("-", $this->untilDate);
                $dariTanggal = $extr[0] . "-01-01";
                $d = new DateTime($this->untilDate, new \DateTimeZone('UTC'));
                $d->modify('first day of previous month');
                $year = $d->format('Y');
                $month = $d->format('m');
                $sampaiTanggal = $year . "-" . $month . "-31";
                $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($dariTanggal, $sampaiTanggal);
                $saldoCoaItem += $saldoCoaItemSaldoLaba['ebt'];
            } else if ($it->coa_category == 'saldo_laba_bulan_berjalan') {
                $extr = explode("-", $this->untilDate);
                $driTanggal = $extr[0] . "-" . $extr[1] . "-01";
                $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($driTanggal, $this->untilDate);
                $saldoCoaItem += $saldoCoaItemSaldoLaba['ebt'];
            }
            if (empty($saldoCoaItem)) {
                $saldoCoaItem = "0";
            }
            return number_format($saldoCoaItem, SettingRepo::getSeparatorFormat());
        }
        return "";
    }
}

