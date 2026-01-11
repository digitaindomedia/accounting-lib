<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockAwalExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return collect($this->data)->map(function ($item) {
            return [
                'stock_date' => $item->stock_date,
                'product_code' => !empty($item->product) ? $item->product->item_code : "",
                'product_name' => !empty($item->product) ? $item->product->item_name : "",
                'warehouse' => !empty($item->warehouse) ? $item->warehouse->warehouse_name : "",
                'qty' => $item->qty,
                'unit' => !empty($item->unit) ? $item->unit->unit_name : "",
                'nominal' => $item->nominal,
                'total' => $item->total,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kode Barang',
            'Nama Barang',
            'Gudang',
            'Qty',
            'Satuan',
            'Harga Satuan',
            'Total',
        ];
    }
}
