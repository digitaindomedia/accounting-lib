<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BukuPembantuExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'ref_date' => $item->ref_date,
                'ref_no' => $item->ref_no,
                'note' => $item->note,
                'field_name' => $item->field_name,
                'nominal' => $item->nominal,
                'left_bill' => $item->left_bill ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'No Ref',
            'Keterangan',
            'Nama Field',
            'Nominal',
            'Sisa Tagihan',
        ];
    }
}
