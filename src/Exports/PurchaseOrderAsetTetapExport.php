<?php

namespace Icso\Accounting\Exports;

use App\Repositories\Tenant\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseOrderAsetTetapExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'nama_aset' => $item->nama_aset,
                'no_aset' => $item->no_aset,
                'aset_tetap_date' => $item->aset_tetap_date,
                'harga_beli' => number_format($item->harga_beli, SettingRepo::getSeparatorFormat()),
                'status_aset_tetap' => $item->status_aset_tetap,
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Nama Aset',
            'No Order Pembelian',
            'Tanggal Beli',
            'Harga Beli',
            'Status',
        ];
    }
}
