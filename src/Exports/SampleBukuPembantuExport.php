<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleBukuPembantuExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Tanggal(thn-bulan-tgl)',
            'No Ref',
            'Keterangan',
            'Nama Field',
            'Nominal',
        ];
    }

    public function array(): array
    {
        return [
            ['2024-07-01', 'REF001', 'Saldo Awal Buku Pembantu A', 'Field A', 1000000],
            ['2024-07-02', 'REF002', 'Saldo Awal Buku Pembantu B', 'Field B', 2000000],
        ];
    }
}
