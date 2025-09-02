<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BukuBesarExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize, WithStyles
{
    protected $jurnalData;

    public function __construct($jurnalData)
    {
        $this->jurnalData = $jurnalData;
    }

    public function collection()
    {
        return collect($this->jurnalData);
    }

    public function headings(): array
    {
        return [
            ['Tanggal', 'Nomor', 'Keterangan', 'Debet', 'Kredit', 'Saldo']
        ];
    }

    public function getData()
    {
        return $this->jurnalData;
    }

    public function map($jurnal): array
    {
        $mappedData = [];
        $mappedData[] = [
            "{$jurnal['coa']['coa_name']} - {$jurnal['coa']['coa_code']}",'', '', '', '', ''
        ];
        $mappedData[] = [
            'Saldo Awal','', '', '', '', number_format($jurnal['saldo_awal'], SettingRepo::getSeparatorFormat())
        ];

        foreach ($jurnal['data'] as $val) {
            $mappedData[] = [
                $val['transaction_date'],
                $val['transaction_no'],
                $val['note'],
                number_format($val['debet'], SettingRepo::getSeparatorFormat()),
                number_format($val['kredit'], SettingRepo::getSeparatorFormat()),
                number_format($val['saldo'], SettingRepo::getSeparatorFormat())
            ];
        }
        $mappedData[] = [
            '','', '', '', '', ''
        ];

        return $mappedData;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $cellRange = 'A1:F1';
                $event->sheet->getDelegate()->getStyle($cellRange)->getFont()->setBold(true);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('D')->getAlignment()->setHorizontal('right');
        $sheet->getStyle('E')->getAlignment()->setHorizontal('right');
        $sheet->getStyle('F')->getAlignment()->setHorizontal('right');
    }
}
