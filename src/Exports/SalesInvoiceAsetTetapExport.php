<?php

namespace Icso\Accounting\Exports;

use App\Repositories\Tenant\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesInvoiceAsetTetapExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'sales_date' => $item->sales_date,
                'sales_no' => $item->sales_no,
                'namaaset' => !empty($item->asettetap) ? $item->asettetap->no_aset : "",
                'buyer_name' => $item->buyer_name,
                'nominal' => number_format($item->price, SettingRepo::getSeparatorFormat()),
                'profit_loss' => number_format($item->profit_loss, SettingRepo::getSeparatorFormat()),
                'sales_status' => $item->sales_status
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Invoice',
            'Nama Aset',
            'Nama Pembeli',
            'Harga Jual',
            'Untung/Rugi',
            'Status'
        ];
    }
}
