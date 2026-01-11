<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JurnalInvoiceExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'invoice_date' => $item->invoice_date,
                'invoice_no' => $item->invoice_no,
                'vendor' => !empty($item->vendor) ? $item->vendor->vendor_company_name : "",
                'coa' => !empty($item->coa) ? $item->coa->coa_code . ' - ' . $item->coa->coa_name : "",
                'note' => $item->note,
                'grandtotal' => $item->grandtotal,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tanggal Invoice',
            'No Invoice',
            'Nama Vendor',
            'Akun (COA)',
            'Keterangan',
            'Nominal',
        ];
    }
}
