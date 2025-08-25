<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JurnalUmumExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'note' => $item->note,
                'total' => $item->total_debet
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
          'Tanggal',
          'No Jurnal',
          "keterangan",
          "Nominal"
        ];
    }
}
