<?php

namespace Icso\Accounting\Exports;


use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PaymentMetodExport  implements FromCollection, WithHeadings, ShouldAutoSize
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
                'payment_name' => $item->payment_name,
                'coa' => !empty($item->coa_id) ? CoaRepo::getCoaById($item->coa_id)->coa_name : '',
                'description' => $item->descriptions,
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Nama Pembayaran',
            'Akun',
            'Deskripsi'
        ];
    }
}
