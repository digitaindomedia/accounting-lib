<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JurnalKasBankExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'jurnal_date' => $item->jurnal_date,
                'jurnal_no' => $item->jurnal_no,
                'akun' => !empty($item->coa) ? $item->coa->coa_name."-".$item->coa_code : "",
                'note' => $item->note,
                'income' => $item->income,
                'outcome' => $item->outcome,
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Jurnal',
            "Akun",
            "Keterangan",
            'Masuk',
            'Keluar'
        ];
    }
}
