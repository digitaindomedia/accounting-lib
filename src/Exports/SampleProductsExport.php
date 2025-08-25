<?php

namespace Icso\Accounting\Exports;

use App\Utils\ProductType;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleProductsExport implements FromArray, WithHeadings
{
    protected $productType;

    public function __construct($productType)
    {
        $this->productType = $productType;
    }
    public function headings(): array
    {
        if($this->productType == ProductType::ITEM){
            return [
                'Kode Barang',
                'Nama Barang',
                'Harga',
                'Status Harga(fix/off)',
                'Satuan',
                'Kategori',
                'Kode Akun Sediaan'
            ];
        }
        return [
            'Kode Jasa',
            'Nama Jasa',
            'Harga',
            'Status Harga(fix/off)',
            'Satuan',
            'Kategori',
            'Kode Akun Biaya',
            'Kode Akun Pendapatan'
        ];
    }

    public function array(): array
    {
        // TODO: Implement array() method.
        return [
            ['kode item', 'nama Item', 100, 1, 'nama satuan', 'nama kategori', '140.04'],
        ];
    }
}
