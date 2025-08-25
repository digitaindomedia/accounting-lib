<?php

namespace Icso\Accounting\Exports;

use App\Utils\ProductType;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SamplePurchaseOrderExport implements FromArray, WithHeadings
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
                ['PO001', '2024-07-01', "2024-07-20", "A00003","keterangan order pembelian", 0, "fix", "exclude", "B001", 100, 2000000, 0, "fix",11],
                ['PO001', '2024-07-01', "2024-07-20", "A00003","keterangan order pembelian", 0, "fix", "exclude", "B002", 200, 2000000, 0, "fix",0],
            ];
        } else {
            return [
                ['PO001', '2024-07-01', '650.01', "A00003" ,"keterangan order pembelian", 0, "fix", "exclude", "Perbaikan AC", 100, 2000000, 0, "fix",11],
                ['PO001', '2024-07-01', '650.01', "A00003" ,"keterangan order pembelian", 0, "fix", "exclude", "Jasa Perbaikan Motor", 200, 2000000, 0, "fix",0],
            ];
        }

    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        if($this->orderType == ProductType::ITEM){
            return [
                'Nomor Order Pembelian',
                'Tanggal Order(thn-bulan-tgl)',
                'Tanggal Pengiriman(thn-bulan-tgl)',
                'Kode Supplier',
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
                'Nomor Order Pembelian',
                'Tanggal Order(thn-bulan-tgl)',
                'Kode Akun Biaya',
                'Kode Supplier',
                'Keterangan',
                'Diskon',
                'Tipe Diskon Total(persen,fix)',
                'Tipe PPN(include/exclude)',
                'Nama Jasa',
                'Kuantiti',
                'Harga Satuan',
                'Diskon Item',
                'Tipe Diskon Item(persen,fix)',
                'Persen PPN(lihat dimaster data pajak)'
            ];
        }
    }
}
