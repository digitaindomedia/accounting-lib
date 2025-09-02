<?php

namespace Icso\Accounting\Exports;


use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesInvoiceExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'invoice_date' => $item->invoice_date,
                'invoice_no' => $item->invoice_no,
                'order' => !empty($item->order) ? $item->order->order_no : "",
                'vendor' => !empty($item->vendor) ? $item->vendor->vendor_company_name : "",
                'due_date' => $item->due_date,
                'grandtotal' => number_format($item->grandtotal, SettingRepo::getSeparatorFormat()),
                'invoice_status' => $item->invoice_status,
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Invoice',
            'No Order Pembelian',
            'Nama Customer',
            'Tanggal Jatuh Tempo',
            'Total Tagihan',
            'Status',
        ];
    }
}
