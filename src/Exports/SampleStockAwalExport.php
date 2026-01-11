<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleStockAwalExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Kode Barang',
            'Kode Gudang',
            'Qty',
            'Harga Satuan',
            'Keterangan',
        ];
    }

    public function array(): array
    {
        return [
            ['B001', 'G001', 10, 100000, 'Saldo Awal Barang A'],
            ['B002', 'G001', 20, 50000, 'Saldo Awal Barang B'],
        ];
    }
}
