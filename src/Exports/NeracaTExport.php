<?php

namespace Icso\Accounting\Exports;


use DateTime;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\SaldoAwal;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\Constants;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NeracaTExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $coaAsset;
    protected $coaLiabilitiesEquity;
    protected $processedIds;
    protected $request;
    protected $fromDate;
    protected $untilDate;
    protected $periode;
    protected $waktu;
    protected $coaRepo;

    public function __construct(Request $request)
    {
        $this->coaAsset = Coa::where('neraca', TypeEnum::IS_NERACA)
            ->where('neraca_type', 'asset')
            ->with('children')
            ->get();

        $this->coaLiabilitiesEquity = Coa::where('neraca', TypeEnum::IS_NERACA)
            ->where(function($query) {
                $query->where('neraca_type', 'liabilitas')
                    ->orWhere('neraca_type', 'ekuitas');
            })
            ->with('children')
            ->get();

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
            'COA Name (Assets)',
            'Saldo (Assets)',
            'COA Name (Liabilities/Equity)',
            'Saldo (Liabilities/Equity)',
        ];
    }

    public function map($row): array
    {
        return [
            $row['coa_name_asset'] ?? '',
            $row['saldo_asset'] ?? '',
            $row['coa_name_liability_equity'] ?? '',
            $row['saldo_liability_equity'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B')->getAlignment()->setHorizontal('right');
        $sheet->getStyle('D')->getAlignment()->setHorizontal('right');
    }

    public function getData()
    {
        $data = [];
        $assetData = $this->getMappedData($this->coaAsset, 'asset');
        $liabilityEquityData = $this->getMappedData($this->coaLiabilitiesEquity, 'liability_equity');

        $maxRows = max(count($assetData), count($liabilityEquityData));
        $totalAktiva = 0;
        $totalPassiva = 0;
        for ($i = 0; $i < $maxRows; $i++) {
            $data[] = array_merge(
                $assetData[$i] ?? ['coa_name_asset' => '', 'saldo_asset' => ''],
                $liabilityEquityData[$i] ?? ['coa_name_liability_equity' => '', 'saldo_liability_equity' => '']
            );
            $totalAktiva = $assetData[$i]['saldo_asset'];
            if(!empty($liabilityEquityData[$i]['level'])) {
                if ($liabilityEquityData[$i]['level'] == '1') {
                    if (!empty($liabilityEquityData[$i]['saldo_liability_equity'])) {
                        $totalPassiva = $totalPassiva + Utility::remove_commas($liabilityEquityData[$i]['saldo_liability_equity']);
                    }

                }
            }

        }
        $data[] = array_merge(
            ['coa_name_asset' => 'Total Aktiva', 'saldo_asset' => $totalAktiva],
            ['coa_name_liability_equity' => 'Total Passiva', 'saldo_liability_equity' => number_format($totalPassiva, SettingRepo::getSeparatorFormat())]
        );

        return $data;
    }

    protected function getMappedData($coaCollection, $type)
    {
        $data = [];
        foreach ($coaCollection as $item) {
            $data = array_merge($data, $this->mapItem($item, 0, $type));
        }
        return $data;
    }

    protected function mapItem($item, $level, $type)
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
        if ($type == 'asset') {
            $data[] = [
                'coa_name_asset' => $prefix . $coaCodeName,
                'saldo_asset' => $this->getSaldo($item, $item->coa_level),
                'level' => $level,
            ];
        } else {
            $data[] = [
                'coa_name_liability_equity' => $prefix . $coaCodeName,
                'saldo_liability_equity' => $this->getSaldo($item, $item->coa_level),
                'level' => $level,
            ];
        }

        foreach ($item->children as $child) {
            $childData = $this->mapItem($child, $level + 1, $type);
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
            if ($type == 'asset') {
                $data[] = [
                    'coa_name_asset' => $prefix . 'Total ' . $item->coa_name,
                    'saldo_asset' => number_format($saldoCoa, SettingRepo::getSeparatorFormat()),
                    'level' => $item->coa_level
                ];
            } else {
                $data[] = [
                    'coa_name_liability_equity' => $prefix . 'Total ' . $item->coa_name,
                    'saldo_liability_equity' => number_format($saldoCoa, SettingRepo::getSeparatorFormat()),
                    'level' => $item->coa_level
                ];
            }
        }

        return $data;
    }

    private function getSaldo($item, $coaLevel)
    {
        if ($coaLevel == 4) {
            $saldoCoaItem = JurnalTransaksiRepo::sumSaldoAwal($item->id, $this->fromDate, $this->untilDate, 'between');
            if ($item->coa_category == 'saldo_laba') {
                $extr = explode("-", $this->untilDate);
                $date = $extr[0] . "-12-31";
                $newdate = date("Y-m-d", strtotime('-1 year', strtotime($date)));
                $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($newdate, "");
                $saldoCoaItem += $saldoCoaItemSaldoLaba['ebt'];
            } else if ($item->coa_category == 'saldo_laba_tahun_berjalan') {
                $extr = explode("-", $this->untilDate);
                $dariTanggal = $extr[0] . "-01-01";
                $d = new DateTime($this->untilDate, new \DateTimeZone('UTC'));
                $d->modify('first day of previous month');
                $year = $d->format('Y');
                $month = $d->format('m');
                $sampaiTanggal = $year . "-" . $month . "-31";
                $saldoCoaItemSaldoLaba = JurnalTransaksiRepo::labaRugi($dariTanggal, $sampaiTanggal);
                $saldoCoaItem += $saldoCoaItemSaldoLaba['ebt'];
            } else if ($item->coa_category == 'saldo_laba_bulan_berjalan') {
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

