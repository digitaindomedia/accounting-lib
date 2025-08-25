<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SamplePemakaianExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'No Pemakaian',
            'Tanggal(thn-bulan-tgl)',
            'kode Gudang',
            'Keterangan',
            'Kode Barang',
            'Qty',
            'Kode COA Akun Pemakaian',
            'Keterangan Item'
        ];
    }

    public function array(): array
    {
        // TODO: Implement array() method.
        return [
            ['PEM001', '2024-07-01', "G001", "keterangan pemakaian", "B001", 10, "630.01", "Keperluan pemakaian barang A"],
            ['PEM001', '2024-07-01', "G001", "keterangan pemakaian", "B001", 20, "630.01", "Keperluan pemakaian barang B"]
        ];
    }
}
