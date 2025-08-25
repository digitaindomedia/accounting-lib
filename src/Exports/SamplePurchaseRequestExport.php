<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SamplePurchaseRequestExport implements FromArray, WithHeadings
{

    public function array(): array
    {
        // TODO: Implement array() method.
        return [
            ['PER001', '2024-07-01', "2024-07-20", "Budi","keterangan permintaan barang dari budi", "B001", 100, "Keterangan stok barang B001 sudah menipis"],
            ['PER001', '2024-07-01', "2024-07-20", "Budi", "keterangan permintaan barang dari budi", "B002", 200, "Keterangan stok barang B002 sudah menipis"],
        ];
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Nomor Permintaan',
            'Tanggal Permintaan(thn-bulan-tgl)',
            'Tanggal Butuh Permintaan(thn-bulan-tgl)',
            'Permintaan Dari',
            'Keterangan',
            'Kode Barang',
            'Qty',
            'Keterangan Item Barang'
        ];
    }
}
