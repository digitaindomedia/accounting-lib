<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseRequestExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'request_date' => $item->request_date,
                'request_no' => $item->request_no,
                'request_from' => $item->request_from,
                'req_needed_date' => $item->req_needed_date,
                'urgency' => $item->urgency,
                'request_status' => $item->request_status,
            ];
        });
    }

    public function getData()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'No Permintaan',
            'Permintaan Dari',
            'Tanggal Butuh',
            'Sifat Permintaan',
            'Status',
        ];
    }
}
