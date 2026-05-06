<?php

namespace Icso\Accounting\Exports;


use Icso\Accounting\Utils\ProductType;
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
                'Kode Akun Sediaan',
                'Konversi Satuan'
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
        if($this->productType == ProductType::ITEM){
            return [
                ['BRG001', 'Nama Barang', 100, 'fix', 'PCS', 'Nama Kategori', '140.04', 'DUS=12 PCS;BOX=10 DUS'],
            ];
        }

        return [
            ['JSA001', 'Nama Jasa', 100, 'fix', 'JAM', 'Nama Kategori', '510.01', '410.01'],
        ];
    }
}
