<?php

namespace Icso\Accounting\Exports;


use Icso\Accounting\Repositories\Utils\SettingRepo;
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
                'aset_tetap_coa' => $item->aset_tetap_coa ? $item->aset_tetap_coa->coa_code . ' - ' . $item->aset_tetap_coa->coa_name : '',
                'akumulasi_penyusutan_coa' => $item->akumulasi_penyusutan_coa ? $item->akumulasi_penyusutan_coa->coa_code . ' - ' . $item->akumulasi_penyusutan_coa->coa_name : '',
                'penyusutan_coa' => $item->penyusutan_coa ? $item->penyusutan_coa->coa_code . ' - ' . $item->penyusutan_coa->coa_name : '',
                'nilai_penyusutan' => $item->nilai_penyusutan,
                'masa_manfaat' => $item->masa_manfaat,
                'note' => $item->note,
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
            'Akun Aset',
            'Akun Akumulasi Penyusutan',
            'Akun Penyusutan',
            'Persentase Penyusutan',
            'Masa Manfaat',
            'Keterangan',
            'Status',
        ];
    }
}
