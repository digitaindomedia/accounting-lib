<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AdjustmentStockExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'adjustment_date' => $item->adjustment_date,
                'ref_no' => $item->ref_no,
                'warehouse' => !empty($item->warehouse) ? $item->warehouse->warehouse_name : "-",
                'akun' => !empty($item->coa_adjustment) ? $item->coa_adjustment->coa_name." - ".$item->coa_adjustment->coa_code : "-",
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Penyesuaian',
            'Nama Gudang',
            'Akun Penyesuaian'
        ];
    }
}
