<?php

namespace Icso\Accounting\Exports;


use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequest;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseOrderExport implements FromCollection, WithHeadings, ShouldAutoSize
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
            $noPermintaan = "-";
            $findPermintaan = PurchaseRequest::where('id','=',$item->request_id)->first();
            if(!empty($findPermintaan)){
                $noPermintaan = $findPermintaan->request_no;
            }
            return [
                'order_date' => $item->order_date,
                'order_no' => $item->order_no,
                'request_no' => $noPermintaan,
                'vendor' => !empty($item->vendor) ? $item->vendor->vendor_name : "-",
                'grandtotal' => $item->grandtotal, SettingRepo::getSeparatorFormat(),
                'order_status' => $item->order_status,
            ];
        });
    }

    public function getData()
    {
        return $this->data;
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        return [
            'Tanggal',
            'No Order Pembelian',
            'No Permintaan',
            'Nama Supplier',
            'Total',
            'Status',
        ];
    }
}
