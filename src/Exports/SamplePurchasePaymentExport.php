<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SamplePurchasePaymentExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'No Pembayaran',
            'Tanggal(thn-bulan-tgl)',
            'Kode Supplier',
            'Kode Coa Pembayaran',
            'No Invoice',
            'Nominal Pembayaran',
            'Note',
        ];
    }

    public function array(): array
    {
        return [
            ['PAY-P-001', '2026-02-26', 'SUP001', '1101', 'INV-P-001', 500000, 'Pelunasan invoice 1'],
            ['PAY-P-001', '2026-02-26', 'SUP001', '1101', 'INV-P-002', 250000, 'Pelunasan invoice 2'],
            ['PAY-P-002', '2026-02-27', 'SUP002', '1102', 'INV-P-003', 1000000, 'Pelunasan invoice 3'],
        ];
    }
}
