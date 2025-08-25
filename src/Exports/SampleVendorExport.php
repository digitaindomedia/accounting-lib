<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleVendorExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'Kode',
            'Nama Lengkap',
            'Nama Perusahaan',
            'Email',
            'No Telp',
            'Alamat',
            'NPWP'
        ];
    }

    public function array(): array
    {
        // TODO: Implement array() method.
        return [
            ['kode', 'nama','nama_perusahaan','email','no telp','alamat','npwp'],
        ];
    }
}
