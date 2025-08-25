<?php

namespace Icso\Accounting\Exports;

use App\Repositories\Tenant\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesPaymentExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'vendor' => !empty($item->vendor) ? $item->vendor->vendor_company_name : "" ,
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
            'Nama Customer',
            'Total Bayar',
            'Metode Pembayaran',
        ];
    }
}
