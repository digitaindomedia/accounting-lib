<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleSaldoAwalAsetTetapExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Tanggal Perolehan',
            'Nama Aset',
            'Nilai Beli',
            'Kode Akun Aset',
            'Kode Akun Akumulasi Penyusutan',
            'Kode Akun Penyusutan',
            'Persentase Penyusutan',
            'Masa Manfaat (Tahun)',
            'Keterangan',
        ];
    }

    public function array(): array
    {
        return [
            ['2024-01-15', 'Mesin Produksi A', 25000000, '1501', '150101', '6101', '', 5, 'Saldo awal aset tetap'],
            ['2023-07-01', 'Kendaraan Operasional', 180000000, '1502', '150201', '6102', 12.5, '', 'Saldo awal kendaraan'],
        ];
    }
}
