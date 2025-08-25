<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseInvoiceAsetTetapExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'no_aset' => $item->order ? $item->order->no_aset : '-',
                'nama_aset' => $item->order ? $item->order->nama_aset : '-',
                'invoice_status' => $item->invoice_status
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Invoice',
            'No Order',
            'Nama Aset',
            'Status',
        ];
    }
}
