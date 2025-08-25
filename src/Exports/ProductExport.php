<?php

namespace Icso\Accounting\Exports;

use App\Models\Tenant\Master\Coa;
use App\Repositories\Tenant\Master\Product\ProductRepo;
use App\Utils\ProductType;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $data;
    protected $productType;

    public function __construct($data, $productType)
    {
        $this->data = $data;
        $this->productType = $productType;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->data)->map(function ($item) {
            if($this->productType == ProductType::ITEM){
                $coaSediaan = "";
                if(!empty($item->coa_id)){
                    $findCoaSediaan = Coa::where('id', $item->coa_id)->first();
                    if(!empty($findCoaSediaan)){
                        $coaSediaan = $findCoaSediaan->coa_name;
                    }
                }
                return [
                    'item_name' => $item->item_name,
                    'item_code' => $item->item_code,
                    'categories' => ProductRepo::getAllCategoriesById($item->id),
                    'satuan' => !empty(ProductRepo::getSatuanById($item->id)) ? ProductRepo::getSatuanById($item->id)->unit_name: "",
                    'selling_price' => $item->selling_price,
                    'description' => $item->descriptions,
                    'has_tax' => $item->has_tax == 'yes' ? "Ya" : "Tidak",
                    'akun_sediaan' => $coaSediaan,
                ];
            }
            $coaPendapatan = "";
            $coaBiaya = "";
            if(!empty($item->coa_id)){
                $findCoaPendapatan = Coa::where('id', $item->coa_id)->first();
                if(!empty($findCoaPendapatan)){
                    $coaPendapatan = $findCoaPendapatan->coa_name;
                }
            }
            if(!empty($item->coa_biaya_id)){
                $findCoaBiaya = Coa::where('id', $item->coa_biaya_id)->first();
                if(!empty($findCoaBiaya)){
                    $coaBiaya = $findCoaBiaya->coa_name;
                }
            }
            return [
                'item_name' => $item->item_name,
                'item_code' => $item->item_code,
                'categories' => ProductRepo::getAllCategoriesById($item->id),
                'satuan' => !empty(ProductRepo::getSatuanById($item->id)) ? ProductRepo::getSatuanById($item->id)->unit_name: "",
                'selling_price' => $item->selling_price,
                'description' => $item->descriptions,
                'has_tax' => $item->has_tax == 'yes' ? "Ya" : "Tidak",
                'akun_biaya' => $coaBiaya,
                'akun_pendapatan' => $coaPendapatan,
            ];
        });
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
        if($this->productType == ProductType::ITEM) {
            return [
                'Nama',
                'Kode',
                'Nama Kategori',
                'Nama Satuan',
                'Harga',
                'Deskripsi',
                'Kena Pajak',
                'Akun Sediaan',
            ];
        }
        return [
            'Nama',
            'Kode',
            'Nama Kategori',
            'Nama Satuan',
            'Harga',
            'Deskripsi',
            'Kena Pajak',
            'Akun Biaya',
            'Akun Pendapatan',
        ];
    }
}
