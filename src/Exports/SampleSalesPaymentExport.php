<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleSalesPaymentExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'No Pembayaran',
            'Tanggal(thn-bulan-tgl)',
            'Kode Customer',
            'Kode Coa Pembayaran',
            'No Invoice',
            'Nominal Pembayaran',
            'Note',
        ];
    }

    public function array(): array
    {
        return [
            ['PAY-S-001', '2026-02-26', 'CUST001', '1101', 'INV-S-001', 500000, 'Pelunasan invoice 1'],
            ['PAY-S-001', '2026-02-26', 'CUST001', '1101', 'INV-S-002', 250000, 'Pelunasan invoice 2'],
            ['PAY-S-002', '2026-02-27', 'CUST002', '1102', 'INV-S-003', 1000000, 'Pelunasan invoice 3'],
        ];
    }
}
