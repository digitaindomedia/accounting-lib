<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleAdjustmentExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'No Penyesuaian',
            'Tanggal(thn-bulan-tgl)',
            'Kode Coa Akun Penyesuaian',
            'kode Gudang',
            'Keterangan',
            'Kode Barang',
            'Qty Aktual',
            'HPP'
        ];
    }

    public function array(): array
    {
        // TODO: Implement array() method.
        return [
            ['PENY001', '2024-07-01', "630.01","G001", "keterangan penyesuaian", "B001", 10, 20000],
            ['PENY001', '2024-07-01', "630.01","G001", "keterangan penyesuaian", "B002", 12, 20000]
        ];
    }
}
