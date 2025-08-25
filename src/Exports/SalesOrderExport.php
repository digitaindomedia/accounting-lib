<?php

namespace Icso\Accounting\Exports;

use App\Repositories\Tenant\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesOrderExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'order_date' => $item->order_date,
                'order_no' => $item->order_no,
                'vendor' => !empty($item->vendor) ? $item->vendor->vendor_name : "-",
                'grandtotal' => number_format($item->grandtotal, SettingRepo::getSeparatorFormat()),
                'order_status' => $item->order_status,
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Order',
            'Nama Customer',
            'Total',
            'Status'
        ];
    }
}
