<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleJurnalKasBankExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'No Bukti',
            'Tanggal(thn-bulan-tgl)',
            'Kode Coa Kas/Bank/Giro',
            'Jenis Transaksi(masuk/keluar)',
            'Diterima/Dibayar Oleh',
            'Keterangan',
            'Kode Coa Item',
            'Nominal',
            'Keterangan'
        ];
    }

    public function array(): array
    {
        // TODO: Implement array() method.
        return [
            ['K001', '2024-07-01', "100.01", "masuk", "Budi","keterangan buat kas", "710.22", 40000, "Keterangan buat item"],
            ['K001', '2024-07-01', "100.01", "masuk", "Budi","keterangan buat kas", "800.01", 20000, "Keterangan buat item"],
        ];
    }
}
