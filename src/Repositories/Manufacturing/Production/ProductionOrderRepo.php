<?php

namespace Icso\Accounting\Repositories\Manufacturing\Production;

use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Manufacturing\Bom;
use Icso\Accounting\Models\Manufacturing\ProductionOrder;
use Icso\Accounting\Models\Manufacturing\ProductionOrderMaterial;
use Icso\Accounting\Models\Manufacturing\ProductionOrderResult;
use Icso\Accounting\Models\Master\Category;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionOrderRepo extends ElequentRepository
{
    protected $model;
    protected string $lastError = '';

    public function __construct(ProductionOrder $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        $dataSet = $model
            ->when(!empty($search), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ref_no', 'like', '%' . $search . '%')
                        ->orWhere('note', 'like', '%' . $search . '%');
                });
            })
            ->when(!empty($where), function ($query) use ($where) {
                $query->where(function ($q) use ($where) {
                    foreach ($where as $item) {
                        $method = $item['method'];
                        if ($method === 'whereBetween') {
                            $q->$method($item['value']['field'], $item['value']['value']);
                        } else {
                            $q->$method($item['value']);
                        }
                    }
                });
            })
            ->with([
                'warehouse',
                'bom',
                'product',
                'outputUnit',
                'materials',
                'materials.product',
                'materials.unit',
                'materials.sourceCategory',
                'results',
                'results.product',
                'results.unit',
            ])
            ->orderBy('production_date', 'desc')
            ->orderBy('id', 'desc');

        if ($perpage > 0) {
            $dataSet->offset($page)->limit($perpage);
        }

        return $dataSet->get();
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        $model = new $this->model;

        return $model
            ->when(!empty($search), function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ref_no', 'like', '%' . $search . '%')
                        ->orWhere('note', 'like', '%' . $search . '%');
                });
            })
            ->when(!empty($where), function ($query) use ($where) {
                $query->where(function ($q) use ($where) {
                    foreach ($where as $item) {
                        $method = $item['method'];
                        if ($method === 'whereBetween') {
                            $q->$method($item['value']['field'], $item['value']['value']);
                        } else {
                            $q->$method($item['value']);
                        }
                    }
                });
            })
            ->count();
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function store(Request $request, array $other = [])
    {
        $this->lastError = '';
        $id = $request->id;
        $userId = $request->user_id;
        $productionDate = !empty($request->production_date)
            ? Utility::changeDateFormat($request->production_date)
            : date('Y-m-d');
        $plannedQty = (float) $request->planned_qty;
        $actualQty = $this->requestHasValue($request, 'actual_qty') ? (float) $request->actual_qty : $plannedQty;
        $statusProduction = !empty($request->status_production) ? $request->status_production : 'draft';

        $arrData = [
            'ref_no' => !empty($request->ref_no) ? $request->ref_no : $this->generateRefNo(),
            'production_date' => $productionDate,
            'warehouse_id' => $request->warehouse_id,
            'bom_id' => !empty($request->bom_id) ? $request->bom_id : null,
            'product_id' => $request->product_id,
            'output_unit_id' => $request->output_unit_id,
            'planned_qty' => $plannedQty,
            'actual_qty' => $actualQty,
            'hpp_allocation_method' => in_array($request->hpp_allocation_method, ['qty', 'percentage'], true)
                ? $request->hpp_allocation_method
                : 'qty',
            'status_production' => $statusProduction,
            'source_type' => !empty($request->source_type) ? $request->source_type : 'manual',
            'source_id' => !empty($request->source_id) ? $request->source_id : 0,
            'coa_id' => !empty($request->coa_id) ? $request->coa_id : 0,
            'note' => $request->note ?? null,
            'reason' => $request->reason ?? null,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_by'] = $userId;
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $res = $this->create($arrData);
                $productionId = $res->id;
            } else {
                $this->update($arrData, $id);
                $productionId = $id;
                $this->deleteAdditional($id);
            }

            $materials = $this->resolveMaterials($request, $plannedQty, $actualQty);
            $results = $this->resolveResults($request, $actualQty);
            $totalResultGood = array_reduce($results, function ($total, $item) {
                return $total + $this->numericValue($item, 'qty_good', 0);
            }, 0);

            if (!empty($results) && abs($actualQty - $totalResultGood) > 0.0001) {
                throw new \RuntimeException('Qty aktual harus sama dengan total qty good.');
            }

            $materialRows = [];
            foreach ($materials as $item) {
                $qtyPlanned = $this->numericValue($item, 'qty_planned', 0);
                $qtyActual = $this->hasValue($item, 'qty_actual')
                    ? $this->numericValue($item, 'qty_actual', 0)
                    : $qtyPlanned;

                $materialRows[] = ProductionOrderMaterial::create([
                    'production_order_id' => $productionId,
                    'bom_item_id' => $item->bom_item_id ?? 0,
                    'material_source_type' => $item->material_source_type ?? 'product',
                    'source_product_id' => $item->source_product_id ?? ($item->product_id ?? null),
                    'source_category_id' => $item->source_category_id ?? null,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'qty_planned' => $qtyPlanned,
                    'qty_actual' => $qtyActual,
                    'requested_qty_planned' => $this->numericValue($item, 'requested_qty_planned', $qtyPlanned),
                    'requested_qty_actual' => $this->numericValue($item, 'requested_qty_actual', $qtyActual),
                    'hpp' => 0,
                    'subtotal' => 0,
                    'line_type' => $item->line_type ?? 'material',
                    'note' => $item->note ?? null,
                ]);
            }

            $resultRows = [];
            foreach ($results as $item) {
                $resultRows[] = ProductionOrderResult::create([
                    'production_order_id' => $productionId,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'qty_planned' => $this->numericValue($item, 'qty_planned', $this->numericValue($item, 'qty_good', 0)),
                    'qty_good' => $this->numericValue($item, 'qty_good', 0),
                    'qty_waste' => $this->numericValue($item, 'qty_waste', 0),
                    'hpp_allocation_percentage' => $this->numericValue($item, 'hpp_allocation_percentage', 0),
                    'hpp' => 0,
                    'subtotal' => 0,
                    'result_role' => $item->result_role ?? 'main',
                    'note' => $item->note ?? null,
                ]);
            }

            if ($statusProduction === 'finished') {
                $totalGoodQty = array_reduce($resultRows, function ($total, $item) {
                    return $total + (float) $item->qty_good;
                }, 0);

                if ($totalGoodQty <= 0) {
                    throw new \RuntimeException('Qty good harus lebih dari 0 saat produksi selesai.');
                }

                $this->postingInventoryAndJournal($productionId, $materialRows, $resultRows);
            }

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($id)
    {
        ProductionOrderMaterial::where('production_order_id', $id)->delete();
        ProductionOrderResult::where('production_order_id', $id)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PRODUCTION_MATERIAL, $id);
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PRODUCTION_RESULT, $id);
        Inventory::where('transaction_code', TransactionsCode::PRODUCTION_MATERIAL)->where('transaction_id', $id)->delete();
        Inventory::where('transaction_code', TransactionsCode::PRODUCTION_RESULT)->where('transaction_id', $id)->delete();
    }

    protected function resolveMaterials(Request $request, float $plannedQty, float $actualQty): array
    {
        $strictStock = $request->status_production === 'finished' && $this->isStockMinusDisallowed();

        if (!empty($request->materials) && (empty($request->bom_id) || $request->boolean('manual_material_override'))) {
            return $this->expandManualMaterialRows(
                $this->normalizeArrayInput($request->materials),
                (int) $request->warehouse_id,
                !empty($request->production_date) ? Utility::changeDateFormat($request->production_date) : date('Y-m-d'),
                $strictStock
            );
        }

        $bom = null;
        if (!empty($request->bom_id)) {
            $bom = Bom::with('items')->find($request->bom_id);
        }

        if (empty($bom) || $bom->items->isEmpty()) {
            return [];
        }

        $plannedFactor = $bom->output_qty > 0 ? $plannedQty / $bom->output_qty : 1;
        $actualFactor = $bom->output_qty > 0 ? $actualQty / $bom->output_qty : 1;
        $rows = [];

        foreach ($bom->items as $item) {
            $wasteFactor = 1 + (((float) $item->waste_percentage) / 100);
            $rowPlannedQty = ((float) $item->qty) * $plannedFactor * $wasteFactor;
            $rowActualQty = ((float) $item->qty) * $actualFactor * $wasteFactor;
            $sourceType = $item->material_source_type ?: 'product';

            if ($sourceType === 'category') {
                array_push($rows, ...$this->resolveCategoryMaterialRows((object) [
                    'bom_item_id' => $item->id,
                    'material_source_type' => 'category',
                    'source_category_id' => $item->source_category_id,
                    'unit_id' => $item->unit_id,
                    'qty_planned' => $rowPlannedQty,
                    'qty_actual' => $rowActualQty,
                    'line_type' => $item->item_role ?: 'material',
                    'note' => $item->note,
                ], (int) $request->warehouse_id, !empty($request->production_date) ? Utility::changeDateFormat($request->production_date) : date('Y-m-d'), $strictStock));
                continue;
            }

            $rows[] = (object) [
                'bom_item_id' => $item->id,
                'material_source_type' => 'product',
                'source_product_id' => $item->source_product_id ?: $item->product_id,
                'source_category_id' => null,
                'product_id' => $item->source_product_id ?: $item->product_id,
                'unit_id' => $item->unit_id,
                'qty_planned' => $rowPlannedQty,
                'qty_actual' => $rowActualQty,
                'requested_qty_planned' => $rowPlannedQty,
                'requested_qty_actual' => $rowActualQty,
                'line_type' => $item->item_role ?: 'material',
                'note' => $item->note,
            ];
        }

        return $rows;
    }

    protected function expandManualMaterialRows(array $items, int $warehouseId, string $productionDate, bool $strictStock): array
    {
        $rows = [];

        foreach ($items as $item) {
            $sourceType = $item->material_source_type ?? (!empty($item->category_id) || !empty($item->source_category_id) ? 'category' : 'product');
            if ($sourceType === 'category') {
                array_push($rows, ...$this->resolveCategoryMaterialRows($item, $warehouseId, $productionDate, $strictStock));
                continue;
            }

            if (empty($item->product_id)) {
                throw new \RuntimeException('Produk bahan masih kosong.');
            }

            $qtyPlanned = $this->numericValue($item, 'qty_planned', 0);
            $qtyActual = $this->hasValue($item, 'qty_actual') ? $this->numericValue($item, 'qty_actual', 0) : $qtyPlanned;
            $rows[] = (object) [
                'bom_item_id' => $item->bom_item_id ?? 0,
                'material_source_type' => 'product',
                'source_product_id' => $item->source_product_id ?? $item->product_id,
                'source_category_id' => null,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'qty_planned' => $qtyPlanned,
                'qty_actual' => $qtyActual,
                'requested_qty_planned' => $qtyPlanned,
                'requested_qty_actual' => $qtyActual,
                'line_type' => $item->line_type ?? 'material',
                'note' => $item->note ?? null,
            ];
        }

        return $rows;
    }

    protected function resolveCategoryMaterialRows(object $item, int $warehouseId, string $productionDate, bool $strictStock): array
    {
        $categoryId = $item->source_category_id ?? $item->category_id ?? null;
        if (empty($categoryId)) {
            throw new \RuntimeException('Kategori bahan masih kosong.');
        }
        if (empty($item->unit_id)) {
            throw new \RuntimeException('Satuan bahan kategori masih kosong.');
        }

        $requestedPlannedQty = $this->numericValue($item, 'qty_planned', 0);
        $requestedActualQty = $this->hasValue($item, 'qty_actual') ? $this->numericValue($item, 'qty_actual', 0) : $requestedPlannedQty;
        $splitQty = $requestedActualQty > 0 ? $requestedActualQty : $requestedPlannedQty;
        if ($splitQty <= 0) {
            return [];
        }

        $inventoryRepo = new InventoryRepo(new Inventory());
        $products = Product::whereHas('categories', function ($query) use ($categoryId) {
            $query->where('als_category.id', $categoryId);
        })->orderBy('id')->get();
        $categoryName = Category::find($categoryId)?->category_name ?? ('ID ' . $categoryId);

        if ($products->isEmpty()) {
            throw new \RuntimeException('Tidak ada produk dalam kategori bahan "' . $categoryName . '".');
        }

        $remaining = $splitQty;
        $rows = [];
        $totalAvailableStock = 0;
        $emptyStockProducts = [];
        $fallbackMinusProduct = null;
        foreach ($products as $product) {
            if ($remaining <= 0) {
                break;
            }

            $availableStock = $inventoryRepo->getStokByDate($product->id, $warehouseId, $item->unit_id, $productionDate);
            $totalAvailableStock += max(0, (float) $availableStock);
            if ($availableStock <= 0) {
                $fallbackMinusProduct ??= $product;
                $emptyStockProducts[] = $this->formatProductName($product);
                continue;
            }

            $consumeQty = min($availableStock, $remaining);
            $ratio = $splitQty > 0 ? ($consumeQty / $splitQty) : 0;
            $rows[] = (object) [
                'bom_item_id' => $item->bom_item_id ?? 0,
                'material_source_type' => 'category',
                'source_product_id' => null,
                'source_category_id' => $categoryId,
                'product_id' => $product->id,
                'unit_id' => $item->unit_id,
                'qty_planned' => $requestedPlannedQty * $ratio,
                'qty_actual' => $requestedActualQty > 0 ? $consumeQty : 0,
                'requested_qty_planned' => $requestedPlannedQty,
                'requested_qty_actual' => $requestedActualQty,
                'line_type' => $item->line_type ?? 'material',
                'note' => $item->note ?? null,
            ];

            $remaining -= $consumeQty;
        }

        if ($remaining > 0.0001 && !$strictStock) {
            $fallbackMinusProduct ??= $products->first();
            $ratio = $splitQty > 0 ? ($remaining / $splitQty) : 0;
            $rows[] = (object) [
                'bom_item_id' => $item->bom_item_id ?? 0,
                'material_source_type' => 'category',
                'source_product_id' => null,
                'source_category_id' => $categoryId,
                'product_id' => $fallbackMinusProduct->id,
                'unit_id' => $item->unit_id,
                'qty_planned' => $requestedPlannedQty * $ratio,
                'qty_actual' => $requestedActualQty > 0 ? $remaining : 0,
                'requested_qty_planned' => $requestedPlannedQty,
                'requested_qty_actual' => $requestedActualQty,
                'line_type' => $item->line_type ?? 'material',
                'note' => $item->note ?? null,
            ];

            $remaining = 0;
        }

        if ($remaining > 0.0001 && $strictStock) {
            throw new \RuntimeException(
                'Stok kategori "' . $categoryName . '" tidak mencukupi. ' .
                'Kebutuhan: ' . $this->formatQty($splitQty) . ', tersedia: ' . $this->formatQty($totalAvailableStock) . ', kekurangan: ' . $this->formatQty($remaining) . '. ' .
                $this->formatEmptyStockProductMessage($emptyStockProducts)
            );
        }

        if (empty($rows)) {
            throw new \RuntimeException(
                'Stok kategori "' . $categoryName . '" tidak tersedia. ' .
                $this->formatEmptyStockProductMessage($emptyStockProducts)
            );
        }

        return $rows;
    }

    protected function resolveResults(Request $request, float $actualQty): array
    {
        if (!empty($request->results)) {
            return $this->normalizeArrayInput($request->results);
        }

        return [
            (object) [
                'product_id' => $request->product_id,
                'unit_id' => $request->output_unit_id,
                'qty_planned' => $actualQty,
                'qty_good' => $actualQty,
                'qty_waste' => 0,
                'result_role' => 'main',
                'note' => $request->note ?? null,
            ],
        ];
    }

    protected function postingInventoryAndJournal(int $productionId, array $materialRows, array $resultRows): void
    {
        $find = $this->findOne($productionId, [], ['product', 'materials.product', 'results.product']);
        if (empty($find)) {
            return;
        }

        $inventoryRepo = new InventoryRepo(new Inventory());
        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $totalMaterialValue = 0;

        foreach ($materialRows as $item) {
            $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $find->production_date);
            $qtyActual = (float) $item->qty_actual;
            $subtotal = $hpp * $qtyActual;
            $totalMaterialValue += $subtotal;

            $item->update([
                'hpp' => $hpp,
                'subtotal' => $subtotal,
            ]);

            $reqInventory = new Request();
            $reqInventory->coa_id = !empty($item->product?->coa_id) ? $item->product->coa_id : 0;
            $reqInventory->user_id = $find->created_by;
            $reqInventory->inventory_date = $find->production_date;
            $reqInventory->transaction_code = TransactionsCode::PRODUCTION_MATERIAL;
            $reqInventory->transaction_id = $find->id;
            $reqInventory->transaction_sub_id = $item->id;
            $reqInventory->qty_out = $qtyActual;
            $reqInventory->warehouse_id = $find->warehouse_id;
            $reqInventory->product_id = $item->product_id;
            $reqInventory->price = $hpp;
            $reqInventory->note = $find->note ?: 'Pemakaian bahan produksi';
            $reqInventory->unit_id = $item->unit_id;
            $inventoryRepo->store($reqInventory);

            if (!empty($find->coa_id) && !empty($reqInventory->coa_id) && $subtotal > 0) {
                $this->createJournalPair(
                    $jurnalRepo,
                    $find,
                    TransactionsCode::PRODUCTION_MATERIAL,
                    $item->id,
                    (int) $find->coa_id,
                    (int) $reqInventory->coa_id,
                    $subtotal,
                    $find->note ?: 'Pemakaian bahan produksi'
                );
            }
        }

        $totalGoodQty = 0;
        foreach ($resultRows as $item) {
            $totalGoodQty += (float) $item->qty_good;
        }

        $hppAllocationMethod = $find->hpp_allocation_method ?: 'qty';
        if ($hppAllocationMethod === 'percentage') {
            $totalPercentage = 0;
            foreach ($resultRows as $item) {
                $percentage = (float) $item->hpp_allocation_percentage;
                $qtyGood = (float) $item->qty_good;

                if ($percentage > 0 && $qtyGood <= 0) {
                    throw new \RuntimeException('Qty Good hasil dengan persentase alokasi HPP harus lebih dari 0.');
                }

                $totalPercentage += $percentage;
            }

            if (abs($totalPercentage - 100) > 0.0001) {
                throw new \RuntimeException('Total persentase alokasi HPP harus 100%.');
            }
        }

        $resultHpp = $totalGoodQty > 0 ? $totalMaterialValue / $totalGoodQty : 0;

        foreach ($resultRows as $item) {
            $qtyGood = (float) $item->qty_good;
            $subtotal = $resultHpp * $qtyGood;

            if ($hppAllocationMethod === 'percentage') {
                $subtotal = $totalMaterialValue * ((float) $item->hpp_allocation_percentage / 100);
                $resultHpp = $qtyGood > 0 ? $subtotal / $qtyGood : 0;
            }

            $item->update([
                'hpp' => $resultHpp,
                'subtotal' => $subtotal,
            ]);

            $resultProduct = Product::find($item->product_id);
            $resultCoaId = !empty($resultProduct?->coa_id) ? $resultProduct->coa_id : 0;

            $reqInventory = new Request();
            $reqInventory->coa_id = $resultCoaId;
            $reqInventory->user_id = $find->created_by;
            $reqInventory->inventory_date = $find->production_date;
            $reqInventory->transaction_code = TransactionsCode::PRODUCTION_RESULT;
            $reqInventory->transaction_id = $find->id;
            $reqInventory->transaction_sub_id = $item->id;
            $reqInventory->qty_in = $qtyGood;
            $reqInventory->warehouse_id = $find->warehouse_id;
            $reqInventory->product_id = $item->product_id;
            $reqInventory->price = $resultHpp;
            $reqInventory->note = $find->note ?: 'Hasil produksi';
            $reqInventory->unit_id = $item->unit_id;
            $inventoryRepo->store($reqInventory);

            if (!empty($find->coa_id) && !empty($resultCoaId) && $subtotal > 0) {
                $this->createJournalPair(
                    $jurnalRepo,
                    $find,
                    TransactionsCode::PRODUCTION_RESULT,
                    $item->id,
                    $resultCoaId,
                    (int) $find->coa_id,
                    $subtotal,
                    $find->note ?: 'Hasil produksi'
                );
            }
        }
    }

    protected function createJournalPair(
        JurnalTransaksiRepo $jurnalRepo,
        ProductionOrder $production,
        string $transactionCode,
        int $transactionSubId,
        int $debetCoaId,
        int $kreditCoaId,
        float $amount,
        string $note
    ): void {
        $baseData = [
            'transaction_date' => $production->production_date,
            'transaction_datetime' => $production->production_date . ' ' . date('H:i:s'),
            'created_by' => $production->created_by,
            'updated_by' => $production->created_by,
            'transaction_code' => $transactionCode,
            'transaction_id' => $production->id,
            'transaction_sub_id' => $transactionSubId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'transaction_no' => $production->ref_no,
            'transaction_status' => JurnalStatusEnum::OK,
            'note' => $note,
        ];

        $jurnalRepo->create(array_merge($baseData, [
            'coa_id' => $debetCoaId,
            'debet' => $amount,
            'kredit' => 0,
        ]));

        $jurnalRepo->create(array_merge($baseData, [
            'coa_id' => $kreditCoaId,
            'debet' => 0,
            'kredit' => $amount,
        ]));
    }

    protected function normalizeArrayInput($rows): array
    {
        if (empty($rows)) {
            return [];
        }

        if (is_array($rows)) {
            return json_decode(json_encode($rows));
        }

        return $rows;
    }

    protected function requestHasValue(Request $request, string $field): bool
    {
        return $request->has($field) && $request->input($field) !== null && $request->input($field) !== '';
    }

    protected function hasValue(object $row, string $field): bool
    {
        return property_exists($row, $field) && $row->{$field} !== null && $row->{$field} !== '';
    }

    protected function numericValue(object $row, string $field, float $default = 0): float
    {
        return $this->hasValue($row, $field) ? (float) $row->{$field} : $default;
    }

    protected function isStockMinusDisallowed(): bool
    {
        $settingKeys = [
            SettingEnum::STOCK_MINUS,
            SettingEnum::STOK_TIDAK_BOLEH_MINUS,
            'stok_tidak_boleh_minus',
            'stok_tidak_boleh_kurang',
            'stock_not_allowed_minus',
        ];

        $value = '';
        foreach ($settingKeys as $settingKey) {
            $value = SettingRepo::getOptionValue($settingKey);
            if ($value !== '') {
                break;
            }
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    protected function formatProductName(Product $product): string
    {
        $code = trim((string) ($product->item_code ?? ''));
        $name = trim((string) ($product->item_name ?? ('ID ' . $product->id)));

        return $code !== '' ? $name . ' (' . $code . ')' : $name;
    }

    protected function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }

    protected function formatEmptyStockProductMessage(array $emptyStockProducts): string
    {
        $emptyStockProducts = array_values(array_unique(array_filter($emptyStockProducts)));
        if (empty($emptyStockProducts)) {
            return '';
        }

        $shownProducts = array_slice($emptyStockProducts, 0, 5);
        $message = 'Produk stok kosong: ' . implode(', ', $shownProducts);
        $remainingCount = count($emptyStockProducts) - count($shownProducts);
        if ($remainingCount > 0) {
            $message .= ', dan ' . $remainingCount . ' produk lainnya';
        }

        return $message . '.';
    }

    protected function generateRefNo(): string
    {
        return self::generateCodeTransaction(
            new ProductionOrder(),
            KeyNomor::NO_PRODUCTION_ORDER,
            'ref_no',
            'production_date'
        );
    }
}
