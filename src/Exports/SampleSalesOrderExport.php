<?php

namespace Icso\Accounting\Exports;


use Icso\Accounting\Utils\ProductType;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SampleSalesOrderExport implements FromArray, WithHeadings
{
    protected $orderType;

    public function __construct($orderType)
    {
        $this->orderType = $orderType;
    }

    public function array(): array
    {
        // TODO: Implement array() method.
        if($this->orderType == ProductType::ITEM){
            return [
                ['PO001', '2024-07-01', "A00003","keterangan order penjualan", 0, "fix", "exclude", "B001", 100, 2000000, 0, "fix",11],
                ['PO001', '2024-07-01', "A00003","keterangan order penjualan", 0, "fix", "exclude", "B002", 200, 2000000, 0, "fix",0],
            ];
        } else {
            return [
                ['PO001', '2024-07-01', "A00003" ,"keterangan order penjualan", 0, "fix", "exclude", "J001", 100, 2000000, 0, "fix",11],
                ['PO001', '2024-07-01', "A00003" ,"keterangan order penjualan", 0, "fix", "exclude", "J001", 200, 2000000, 0, "fix",0],
            ];
        }

    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        if($this->orderType == ProductType::ITEM){
            return [
                'Nomor Order Penjualan',
                'Tanggal Order(thn-bulan-tgl)',
                'Kode Customer',
                'Keterangan',
                'Diskon',
                'Tipe Diskon Total(persen,fix)',
                'Tipe PPN(include/exclude)',
                'Kode Barang',
                'Kuantiti',
                'Harga Satuan',
                'Diskon Item',
                'Tipe Diskon Item(persen,fix)',
                'Persen PPN(lihat dimaster data pajak)'
            ];
        } else{
            return [
                'Nomor Order Penjualan',
                'Tanggal Order(thn-bulan-tgl)',
                'Kode Customer',
                'Keterangan',
                'Diskon',
                'Tipe Diskon Total(persen,fix)',
                'Tipe PPN(include/exclude)',
                'Kode Jasa',
                'Kuantiti',
                'Harga Satuan',
                'Diskon Item',
                'Tipe Diskon Item(persen,fix)',
                'Persen PPN(lihat dimaster data pajak)'
            ];
        }
    }
}
