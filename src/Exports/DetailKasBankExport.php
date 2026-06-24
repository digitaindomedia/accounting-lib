<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DetailKasBankExport implements FromCollection, WithHeadings, WithMapping, WithEvents, ShouldAutoSize, WithStyles
{
    protected $reportData;

    public function __construct($reportData)
    {
        $this->reportData = $reportData;
    }

    public function collection()
    {
        return collect($this->reportData);
    }

    public function headings(): array
    {
        return [
            ['Tanggal', 'No Transaksi', 'Akun', 'Keterangan', 'Debet', 'Kredit']
        ];
    }

    public function getData()
    {
        return $this->reportData;
    }

    public function map($group): array
    {
        $mappedData = [];

        foreach ($group['rows'] as $index => $row) {
            $mappedData[] = [
                $index === 0 ? $group['transaction_date'] : '',
                $index === 0 ? $group['transaction_no'] : '',
                "{$row['coa']['coa_name']} ({$row['coa']['coa_code']})",
                $row['note'],
                number_format($row['debet'], SettingRepo::getSeparatorFormat()),
                number_format($row['kredit'], SettingRepo::getSeparatorFormat()),
            ];
        }

        $mappedData[] = ['', '', '', '', '', ''];

        return $mappedData;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:F1')->getFont()->setBold(true);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('E')->getAlignment()->setHorizontal('right');
        $sheet->getStyle('F')->getAlignment()->setHorizontal('right');
    }
}
