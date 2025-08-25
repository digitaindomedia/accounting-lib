<?php

namespace Icso\Accounting\Exports;

use App\Repositories\Tenant\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseReturExport implements FromCollection, WithHeadings, ShouldAutoSize
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
                'retur_date' => $item->retur_date,
                'retur_no' => $item->retur_no,
                'receive' => !empty($item->receive) ? $item->receive->receive_no : "",
                'invoice' => !empty($item->invoice) ? $item->invoice->invoice_no : "",
                'vendor' => !empty($item->vendor) ? $item->vendor->vendor_company_name : "",
                'total' => number_format($item->total, SettingRepo::getSeparatorFormat()),
                'retur_status' => $item->retur_status,
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Retur',
            'No Penerimaan',
            'No Invoice',
            'Nama Supplier',
            'Total',
            'Status',
        ];
    }
}
