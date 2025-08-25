<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesDeliveryExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'delivery_date' => $item->delivery_date,
                'delivery_no' => $item->delivery_no,
                'order' => !empty($item->order) ? $item->order->order_no : "",
                'vendor' => !empty($item->order) ? !empty($item->order->vendor) ? $item->order->vendor->vendor_company_name : "" : ""
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Pengiriman',
            'No Order',
            'Nama Customer'
        ];
    }
}
