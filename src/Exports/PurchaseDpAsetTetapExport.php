<?php

namespace Icso\Accounting\Exports;

use App\Repositories\Tenant\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseDpAsetTetapExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'nama_aset' => $item->order ? $item->order->nama_aset : '-',
                'order' => $item->order ? $item->order->no_aset : '-',
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
            'Nama Aset',
            'No Order',
            'Nominal'
        ];
    }
}
