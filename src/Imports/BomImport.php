<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Models\Manufacturing\Bom;
use Icso\Accounting\Repositories\Manufacturing\Bom\BomRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class BomImport implements ToCollection
{
    protected $userId;
    protected $bomRepo;
    private $errors = [];
    private $totalRows = 0;
    private $successCount = 0;
    private $importedIds = [];

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->bomRepo = new BomRepo(new Bom());
    }

    public function collection(Collection $rows)
    {
        $bomGroups = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $this->totalRows++;

            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }

            $bomCode = trim((string) $row[1]);

            if (!isset($bomGroups[$bomCode])) {
                $bomGroups[$bomCode] = [
                    'header' => [
                        'bom_name'              => trim((string) $row[0]),
                        'bom_code'              => $bomCode,
                        'product_code'          => trim((string) $row[2]),
                        'output_qty'            => (float) $row[3],
                        'output_unit_code'      => trim((string) $row[4]),
                        'manufacturing_mode'    => trim((string) $row[5]),
                        'auto_consume_trigger'  => trim((string) $row[6]),
                        'use_case'              => trim((string) $row[7]),
                        'status'                => trim((string) $row[8]),
                        'note'                  => trim((string) ($row[9] ?? '')),
                    ],
                    'items' => [],
                ];
            }

            if (!empty($row[10])) {
                $bomGroups[$bomCode]['items'][] = [
                    'product_code'      => trim((string) $row[10]),
                    'unit_code'         => trim((string) ($row[11] ?? '')),
                    'qty'               => (float) ($row[12] ?? 0),
                    'waste_percentage'  => (float) ($row[13] ?? 0),
                    'is_optional'       => (int) ($row[14] ?? 0),
                    'note'              => trim((string) ($row[15] ?? '')),
                ];
            }
        }

        foreach ($bomGroups as $bomCode => $bomData) {
            try {
                $product = Product::where('item_code', $bomData['header']['product_code'])->first();
                if (!$product) {
                    $this->errors[] = "BOM {$bomCode}: Kode produk hasil '{$bomData['header']['product_code']}' tidak ditemukan.";
                    continue;
                }

                $outputUnit = Unit::where('unit_code', $bomData['header']['output_unit_code'])->first();
                if (!$outputUnit) {
                    $this->errors[] = "BOM {$bomCode}: Kode satuan output '{$bomData['header']['output_unit_code']}' tidak ditemukan.";
                    continue;
                }

                $items = [];
                foreach ($bomData['items'] as $item) {
                    $itemProduct = Product::where('item_code', $item['product_code'])->first();
                    $itemUnit = Unit::where('unit_code', $item['unit_code'])->first();

                    $items[] = [
                        'id'                => null,
                        'product_id'        => $itemProduct ? $itemProduct->id : 0,
                        'unit_id'           => $itemUnit ? $itemUnit->id : 0,
                        'qty'               => $item['qty'],
                        'waste_percentage'  => $item['waste_percentage'],
                        'item_role'         => 'material',
                        'is_optional'       => $item['is_optional'],
                        'note'              => $item['note'],
                    ];
                }

                $request = new Request([
                    'id'                    => null,
                    'bom_name'              => $bomData['header']['bom_name'],
                    'bom_code'              => $bomData['header']['bom_code'],
                    'product_id'            => $product->id,
                    'output_qty'            => $bomData['header']['output_qty'],
                    'output_unit_id'        => $outputUnit->id,
                    'manufacturing_mode'    => $bomData['header']['manufacturing_mode'],
                    'auto_consume_trigger'  => $bomData['header']['auto_consume_trigger'],
                    'use_case'              => $bomData['header']['use_case'],
                    'status'                => $bomData['header']['status'],
                    'note'                  => $bomData['header']['note'],
                    'bom_version'           => '1.0',
                    'user_id'               => $this->userId,
                    'items'                 => $items,
                ]);

                $res = $this->bomRepo->store($request);

                if ($res) {
                    $this->successCount++;
                    $this->importedIds[] = is_object($res) ? $res->id : $res;
                } else {
                    $this->errors[] = "BOM {$bomCode}: Gagal menyimpan data.";
                }
            } catch (\Throwable $e) {
                $this->errors[] = "BOM {$bomCode}: " . $e->getMessage();
            }
        }
    }

    private function hasValidationErrors($index, $row): bool
    {
        $rowNum = $index + 1;

        if (empty($row[0])) {
            $this->errors[] = "Baris {$rowNum}: Nama BOM kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris {$rowNum}: Kode BOM kosong.";
            return true;
        }
        if (empty($row[2])) {
            $this->errors[] = "Baris {$rowNum}: Kode produk hasil kosong.";
            return true;
        }
        if (empty($row[3]) || (float) $row[3] <= 0) {
            $this->errors[] = "Baris {$rowNum}: Qty output harus lebih dari 0.";
            return true;
        }
        if (empty($row[4])) {
            $this->errors[] = "Baris {$rowNum}: Kode satuan output kosong.";
            return true;
        }
        if (!in_array($row[5], ['pre_produce', 'auto_consume', 'both'])) {
            $this->errors[] = "Baris {$rowNum}: Manufacturing mode tidak valid (pre_produce/auto_consume/both).";
            return true;
        }
        if (!in_array($row[6], ['invoice', 'delivery', 'both'])) {
            $this->errors[] = "Baris {$rowNum}: Auto consume trigger tidak valid (invoice/delivery/both).";
            return true;
        }
        if (!in_array($row[8], ['active', 'inactive'])) {
            $this->errors[] = "Baris {$rowNum}: Status tidak valid (active/inactive).";
            return true;
        }

        return false;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImportedIds(): array
    {
        return array_values(array_unique(array_filter($this->importedIds)));
    }
}


class BomImport implements ToCollection
{
    protected $userId;
    protected $bomRepo;
    private $errors = [];
    private $totalRows = 0;
    private $successCount = 0;
    private $importedIds = [];

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->bomRepo = new BomRepo();
    }

    public function collection(Collection $rows)
    {
        $bomGroups = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $this->totalRows++;

            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }

            $bomCode = trim((string) $row[1]);

            if (!isset($bomGroups[$bomCode])) {
                $bomGroups[$bomCode] = [
                    'header' => [
                        'bom_name'              => trim((string) $row[0]),
                        'bom_code'              => $bomCode,
                        'product_code'          => trim((string) $row[2]),
                        'output_qty'            => (float) $row[3],
                        'output_unit_code'      => trim((string) $row[4]),
                        'manufacturing_mode'    => trim((string) $row[5]),
                        'auto_consume_trigger'  => trim((string) $row[6]),
                        'use_case'              => trim((string) $row[7]),
                        'status'                => trim((string) $row[8]),
                        'note'                  => trim((string) ($row[9] ?? '')),
                    ],
                    'items' => [],
                ];
            }

            if (!empty($row[10])) {
                $bomGroups[$bomCode]['items'][] = [
                    'product_code'      => trim((string) $row[10]),
                    'unit_code'         => trim((string) ($row[11] ?? '')),
                    'qty'               => (float) ($row[12] ?? 0),
                    'waste_percentage'  => (float) ($row[13] ?? 0),
                    'is_optional'       => (int) ($row[14] ?? 0),
                    'note'              => trim((string) ($row[15] ?? '')),
                ];
            }
        }

        foreach ($bomGroups as $bomCode => $bomData) {
            DB::beginTransaction();
            try {
                $product = Product::where('item_code', $bomData['header']['product_code'])->first();
                if (!$product) {
                    $this->errors[] = "BOM {$bomCode}: Kode produk hasil '{$bomData['header']['product_code']}' tidak ditemukan.";
                    DB::rollBack();
                    continue;
                }

                $outputUnit = Unit::where('unit_code', $bomData['header']['output_unit_code'])->first();
                if (!$outputUnit) {
                    $this->errors[] = "BOM {$bomCode}: Kode satuan output '{$bomData['header']['output_unit_code']}' tidak ditemukan.";
                    DB::rollBack();
                    continue;
                }

                $items = [];
                foreach ($bomData['items'] as $item) {
                    $itemProduct = Product::where('item_code', $item['product_code'])->first();
                    $itemUnit = Unit::where('unit_code', $item['unit_code'])->first();

                    $items[] = [
                        'id'                => null,
                        'product_id'        => $itemProduct ? $itemProduct->id : 0,
                        'unit_id'           => $itemUnit ? $itemUnit->id : 0,
                        'qty'               => $item['qty'],
                        'waste_percentage'  => $item['waste_percentage'],
                        'item_role'         => 'material',
                        'is_optional'       => $item['is_optional'],
                        'note'              => $item['note'],
                    ];
                }

                $payload = new \stdClass();
                $payload->bom_name              = $bomData['header']['bom_name'];
                $payload->bom_code              = $bomData['header']['bom_code'];
                $payload->product_id            = $product->id;
                $payload->output_qty            = $bomData['header']['output_qty'];
                $payload->output_unit_id        = $outputUnit->id;
                $payload->manufacturing_mode    = $bomData['header']['manufacturing_mode'];
                $payload->auto_consume_trigger  = $bomData['header']['auto_consume_trigger'];
                $payload->use_case              = $bomData['header']['use_case'];
                $payload->status                = $bomData['header']['status'];
                $payload->note                  = $bomData['header']['note'];
                $payload->bom_version           = '1.0';
                $payload->user_id               = $this->userId;
                $payload->items                 = $items;

                $res = $this->bomRepo->store($payload);

                if ($res) {
                    $this->successCount++;
                    $this->importedIds[] = $res->id ?? $res;
                    DB::commit();
                } else {
                    $this->errors[] = "BOM {$bomCode}: Gagal menyimpan data.";
                    DB::rollBack();
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->errors[] = "BOM {$bomCode}: " . $e->getMessage();
            }
        }
    }

    private function hasValidationErrors($index, $row): bool
    {
        $rowNum = $index + 1;

        if (empty($row[0])) {
            $this->errors[] = "Baris {$rowNum}: Nama BOM kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris {$rowNum}: Kode BOM kosong.";
            return true;
        }
        if (empty($row[2])) {
            $this->errors[] = "Baris {$rowNum}: Kode produk hasil kosong.";
            return true;
        }
        if (empty($row[3]) || (float) $row[3] <= 0) {
            $this->errors[] = "Baris {$rowNum}: Qty output harus lebih dari 0.";
            return true;
        }
        if (empty($row[4])) {
            $this->errors[] = "Baris {$rowNum}: Kode satuan output kosong.";
            return true;
        }
        if (!in_array($row[5], ['pre_produce', 'auto_consume', 'both'])) {
            $this->errors[] = "Baris {$rowNum}: Manufacturing mode tidak valid (pre_produce/auto_consume/both).";
            return true;
        }
        if (!in_array($row[6], ['invoice', 'delivery', 'both'])) {
            $this->errors[] = "Baris {$rowNum}: Auto consume trigger tidak valid (invoice/delivery/both).";
            return true;
        }
        if (!in_array($row[8], ['active', 'inactive'])) {
            $this->errors[] = "Baris {$rowNum}: Status tidak valid (active/inactive).";
            return true;
        }

        return false;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImportedIds(): array
    {
        return array_values(array_unique(array_filter($this->importedIds)));
    }
}
