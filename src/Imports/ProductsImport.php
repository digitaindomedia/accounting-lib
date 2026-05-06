<?php

namespace Icso\Accounting\Imports;


use Icso\Accounting\Models\Master\Category;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductCategory;
use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Utils\ProductType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

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

            $conversions = [];
            if ($this->productType == ProductType::ITEM && !$rowHasError) {
                $conversionText = $row[7] ?? null;
                $conversions = $this->parseConversions($conversionText, $unit, $messages);
                if (!empty($messages)) {
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

            $coaBiaya = null;
            // Validasi COA biaya jika SERVICE
           /*
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
            }*/

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
            DB::transaction(function () use ($row, $unit, $coa, $coaBiaya, $conversions) {
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

                $this->storeConversions($product->id, $conversions);
            });

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

    private function parseConversions($conversionText, Unit $productUnit, array &$messages): array
    {
        if (empty($conversionText)) {
            return [];
        }

        $conversions = [];
        $conversionUnitIds = [];
        $parts = array_filter(array_map('trim', explode(';', $conversionText)));

        foreach ($parts as $part) {
            if (!preg_match('/^([^\s=]+)\s*=\s*([0-9]+(?:[.,][0-9]+)?)\s+([^\s=]+)$/', $part, $matches)) {
                $messages[] = "Format konversi '{$part}' salah. Gunakan contoh: DUS=12 PCS;BOX=10 DUS.";
                continue;
            }

            $unitCode = trim($matches[1]);
            $value = (float) str_replace(',', '.', $matches[2]);
            $baseUnitCode = trim($matches[3]);

            $unit = Unit::where('unit_code', $unitCode)->first();
            $baseUnit = Unit::where('unit_code', $baseUnitCode)->first();

            if (!$unit) {
                $messages[] = "Satuan konversi '{$unitCode}' tidak ditemukan.";
                continue;
            }

            if (!$baseUnit) {
                $messages[] = "Satuan dasar konversi '{$baseUnitCode}' tidak ditemukan.";
                continue;
            }

            if ($value <= 0) {
                $messages[] = "Nilai konversi '{$part}' harus lebih besar dari 0.";
                continue;
            }

            if ($unit->id == $productUnit->id) {
                $messages[] = "Satuan konversi '{$unitCode}' tidak boleh sama dengan satuan utama barang.";
                continue;
            }

            if ($unit->id == $baseUnit->id) {
                $messages[] = "Satuan konversi '{$unitCode}' tidak boleh sama dengan satuan dasarnya.";
                continue;
            }

            if (isset($conversionUnitIds[$unit->id])) {
                $messages[] = "Satuan konversi '{$unitCode}' duplikat.";
                continue;
            }

            $conversion = [
                'unit_id' => $unit->id,
                'unit_code' => $unitCode,
                'nilai' => $value,
                'base_unit_id' => $baseUnit->id,
                'base_unit_code' => $baseUnitCode,
            ];

            $conversions[] = $conversion;
            $conversionUnitIds[$unit->id] = true;
        }

        if (!empty($messages)) {
            return [];
        }

        $conversionsByUnitId = [];
        foreach ($conversions as $conversion) {
            $conversionsByUnitId[$conversion['unit_id']] = $conversion;
        }

        foreach ($conversions as $conversion) {
            if ($this->calculateSmallestValue($conversion, $conversionsByUnitId, $productUnit->id) === null) {
                $messages[] = "Rantai konversi '{$conversion['unit_code']}' harus berakhir ke satuan utama '{$productUnit->unit_code}'.";
            }
        }

        return empty($messages) ? $conversions : [];
    }

    private function storeConversions(int $productId, array $conversions): void
    {
        if (empty($conversions)) {
            return;
        }

        $conversionsByUnitId = [];
        foreach ($conversions as $conversion) {
            $conversionsByUnitId[$conversion['unit_id']] = $conversion;
        }

        foreach ($conversions as $conversion) {
            ProductConvertion::create([
                'product_id' => $productId,
                'unit_id' => $conversion['unit_id'],
                'nilai' => $conversion['nilai'],
                'nilai_terkecil' => $this->calculateSmallestValue($conversion, $conversionsByUnitId),
                'base_unit_id' => $conversion['base_unit_id'],
                'price' => 0,
            ]);
        }
    }

    private function calculateSmallestValue(array $conversion, array $conversionsByUnitId, ?int $productUnitId = null, array $visited = []): ?float
    {
        if (isset($visited[$conversion['unit_id']])) {
            return null;
        }

        $visited[$conversion['unit_id']] = true;

        if ($productUnitId !== null && $conversion['base_unit_id'] == $productUnitId) {
            return $conversion['nilai'];
        }

        if (!isset($conversionsByUnitId[$conversion['base_unit_id']])) {
            return $productUnitId === null ? $conversion['nilai'] : null;
        }

        $baseSmallestValue = $this->calculateSmallestValue(
            $conversionsByUnitId[$conversion['base_unit_id']],
            $conversionsByUnitId,
            $productUnitId,
            $visited
        );

        if ($baseSmallestValue === null) {
            return null;
        }

        return $conversion['nilai'] * $baseSmallestValue;
    }
}
