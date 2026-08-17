<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleBomExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Nama BOM',
            'Kode BOM',
            'Kode Produk Hasil',
            'Qty Output',
            'Kode Satuan Output',
            'Manufacturing Mode (pre_produce/auto_consume/both)',
            'Auto Consume Trigger (invoice/delivery/both)',
            'Use Case',
            'Status (active/inactive)',
            'Catatan',
            'Kode Produk Bahan',
            'Kode Satuan Bahan',
            'Qty Bahan',
            'Waste Percentage',
            'Is Optional (0/1)',
            'Catatan Bahan',
        ];
    }

    public function array(): array
    {
        return [
            ['BOM Produk A', 'BOM001', 'PA001', 10, 'PCS', 'pre_produce', 'invoice', 'general', 'active', '', 'BA001', 'KG', 5, 0, 0, ''],
            ['BOM Produk A', 'BOM001', 'PA001', 10, 'PCS', 'pre_produce', 'invoice', 'general', 'active', '', 'BA002', 'PCS', 2, 2, 0, 'opsional'],
        ];
    }
}
