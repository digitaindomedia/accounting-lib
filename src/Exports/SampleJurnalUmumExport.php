<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleJurnalUmumExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'No Jurnal',
            'Tanggal(thn-bulan-tgl)',
            'Keterangan',
            'Kode Coa',
            'Debet',
            'Kredit'
        ];
    }

    public function array(): array
    {
        // TODO: Implement array() method.
        return [
            ['J001', '2024-07-01', "keterangan buat debet", "100,01", 40000, 0],
            ['J001', '2024-07-01', "keterangan buat kredit", "100,02", 0, 40000],
        ];
    }
}
