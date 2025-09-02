<?php

namespace Icso\Accounting\Imports;


use Icso\Accounting\Models\Master\Category;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductCategory;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Utils\ProductType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithValidation;

class ProductsImport implements ToCollection
{
    protected $userId;
    protected $productType;
    private $errors = [];
    private $successCount = 0;
    private $rowResults = [];
    private $totalRows = 0;
    public function __construct($userId,$productType)
    {
        $this->userId = $userId;
        $this->productType = $productType;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Skip header row
            $this->totalRows++;
            $rowNumber = $index + 1;
            $rowData = [
                'row' => $rowNumber,
                'note' => "Kode '{$row[0]}' berhasil import." ?? null,
                'status' => 'success',
                'message' => 'Data berhasil diimport.'
            ];

            $rowHasError = false;
            $messages = [];

            // Validasi item_code
            if (empty($row[0])) {
                $messages[] = "Kode kosong.";
                $rowHasError = true;
            } elseif (Product::where('item_code', $row[0])->exists()) {
                $messages[] = "Kode '{$row[0]}' sudah ada.";
                $rowHasError = true;
            }

            // Validasi item_name
            if (empty($row[1])) {
                $messages[] = "Nama barang kosong.";
                $rowHasError = true;
            }

            // Validasi satuan
            $unit = null;
            if (empty($row[4])) {
                $messages[] = "Satuan kosong.";
                $rowHasError = true;
            } else {
                $unit = Unit::where('unit_code', $row[4])->first();
                if (!$unit) {
                    $messages[] = "Satuan '{$row[4]}' tidak ditemukan.";
                    $rowHasError = true;
                }
            }

            // Validasi COA
            $coa = null;
            if (empty($row[6])) {
                $messages[] = "Kode akun kosong.";
                $rowHasError = true;
            } else {
                $coa = Coa::where('coa_code', $row[6])->first();
                if (!$coa) {
                    $messages[] = "Kode akun '{$row[6]}' tidak ditemukan.";
                    $rowHasError = true;
                }
            }

            // Validasi COA biaya jika SERVICE
            $coaBiaya = 0;
            if ($this->productType == ProductType::SERVICE) {
                if (empty($row[7])) {
                    $messages[] = "Kode akun biaya kosong.";
                    $rowHasError = true;
                } else {
                    $coaBiaya = Coa::where('coa_code', $row[7])->first();
                    if (!$coaBiaya) {
                        $messages[] = "Kode akun biaya '{$row[7]}' tidak ditemukan.";
                        $rowHasError = true;
                    }
                }
            }

            // Jika error
            if ($rowHasError) {
                $rowData['status'] = 'error';
                $rowData['message'] = implode(' ', $messages);
                $rowData['note'] = implode(' ', $messages);
                $this->errors[] = "Baris {$rowNumber}: {$rowData['message']}";
                $this->rowResults[] = $rowData;
                continue;
            }

            // Simpan produk
            $product = Product::create([
                'item_code' => $row[0],
                'item_name' => $row[1],
                'selling_price' => !empty($row[2]) ? $row[2] : 0,
                'status_price' => !empty($row[3]) && strtolower($row[3]) === 'fix' ? 1 : 0,
                'unit_id' => $unit->id,
                'coa_id' => $coa->id,
                'coa_biaya_id' => !empty($coaBiaya) ? $coaBiaya->id : 0,
                'product_type' => $this->productType,
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);

            // Simpan kategori jika ada
            if (!empty($row[5])) {
                $category = Category::where('category_name', $row[5])->first();
                if ($category) {
                    ProductCategory::create([
                        'product_id' => $product->id,
                        'category_id' => $category->id,
                    ]);
                } else {
                    $createCategory = Category::create([
                        'category_name' => $row[5],
                        'category_type' => $this->productType,
                        'category_description' => '',
                        'created_by' => $this->userId,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'updated_by' => $this->userId
                    ]);
                    if($createCategory){
                        ProductCategory::create([
                            'product_id' => $product->id,
                            'category_id' => $createCategory->id,
                        ]);
                    }
                }
            }

            $this->successCount++;
            $this->rowResults[] = $rowData;
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getRowResults()
    {
        return $this->rowResults;
    }

    public function getTotalRows()
    {
        return $this->totalRows;
    }
}
