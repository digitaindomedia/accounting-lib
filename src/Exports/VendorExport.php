<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VendorExport  implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $data;
    protected $vendorType;

    public function __construct($data,$vendorType)
    {
        $this->data = $data;
        $this->vendorType = $vendorType;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->data)->map(function ($item) {
            return [
                'vendor_name' => $item->vendor_name,
                'vendor_code' => $item->vendor_code,
                'vendor_company_name' => $item->vendor_company_name,
                'vendor_email' => $item->vendor_email,
                'vendor_phone' => $item->vendor_phone,
                'vendor_npwp' => $item->vendor_npwp,
                'vendor_address' => $item->vendor_address
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Nama',
            'Kode',
            'Nama Perusahaan',
            'Email',
            'No Telp',
            'NPWP',
            'Alamat'
        ];
    }
}
