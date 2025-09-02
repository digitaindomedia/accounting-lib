<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesDownPaymentExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'downpayment_date' => $item->downpayment_date,
                'ref_no' => $item->ref_no,
                'order' => !empty($item->order) ? $item->order->order_no : "",
                'vendor' => !empty($item->order) ? !empty($item->order->vendor) ? $item->order->vendor->vendor_company_name : "" : "",
                'nominal' => number_format($item->nominal, SettingRepo::getSeparatorFormat())
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Transaksi',
            'No Order',
            'Nama Customer',
            'Nominal'
        ];
    }
}
