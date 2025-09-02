<?php

namespace Icso\Accounting\Exports;


use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TaxExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'tax_name' => $item->tax_name,
                'tax_percentage' => $item->tax_percentage,
                'tax_description' => $item->tax_description,
                'tax_sign' => $item->tax_sign == TypeEnum::TAX_SIGN_TYPE_PUNGUT ? "Pungut" : "Potong",
                'purchase_coa_id' => !empty($item->purchase_coa_id) ? CoaRepo::getCoaById($item->purchase_coa_id)->coa_name : "" ,
                'sales_coa_id' => !empty($item->sales_coa_id) ? CoaRepo::getCoaById($item->sales_coa_id)->coa_name : "" ,
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Nama Pajak',
            'Persentase',
            'Deskripsi',
            'Jenis Pajak',
            'Akun Pembelian',
            'Akun Penjualan',
        ];
    }
}
