<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SaldoAwalExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Akun COA', 'Debet', 'Kredit'
        ];
    }

    public function getData()
    {
        return $this->data;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('B')->getAlignment()->setHorizontal('right');
        $sheet->getStyle('C')->getAlignment()->setHorizontal('right');
    }
}
