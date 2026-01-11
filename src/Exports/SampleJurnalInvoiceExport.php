<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleJurnalInvoiceExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Nomor Invoice',
            'Tanggal Invoice(thn-bulan-tgl)',
            'Kode Vendor',
            'Kode Akun (COA)',
            'Keterangan',
            'Nominal',
        ];
    }

    public function array(): array
    {
        return [
            ['INV-001', '2024-07-01', 'V001', '1101', 'Keterangan Jurnal Invoice', 1000000],
            ['INV-002', '2024-07-02', 'V002', '1102', 'Keterangan Jurnal Invoice 2', 2000000],
        ];
    }
}
