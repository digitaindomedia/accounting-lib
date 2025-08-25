<?php

namespace Icso\Accounting\Exports;

use App\Repositories\Tenant\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchasePaymentAsetTetapExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'payment_date' => $item->payment_date,
                'payment_no' => $item->payment_no,
                'invoice' => !empty($item->invoice) ? $item->invoice->invoice_no : "" ,
                'total' => number_format($item->total, SettingRepo::getSeparatorFormat()),
                'method' => !empty($item->payment_method) ? $item->payment_method->payment_name : "",
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Pelunasan',
            'No Invoice',
            'Total Bayar',
            'Metode Pembayaran'
        ];
    }
}
