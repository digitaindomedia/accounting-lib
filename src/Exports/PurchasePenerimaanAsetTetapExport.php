<?php

namespace Icso\Accounting\Exports;


use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchasePenerimaanAsetTetapExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'receive_date' => $item->receive_date,
                'receive_no' => $item->receive_no,
                'no_aset' => $item->order ? $item->order->no_aset : '-',
                'nama_aset' => $item->order ? $item->order->nama_aset : '-',
                'receive_status' => $item->receive_status
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Penerimaan',
            'No Order',
            'Nama Aset',
            'Status',
        ];
    }
}
