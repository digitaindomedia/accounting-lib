<?php

namespace Icso\Accounting\Repositories\Manufacturing\Bom;

use Icso\Accounting\Models\Manufacturing\Bom;
use Icso\Accounting\Models\Manufacturing\BomItem;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\ManufacturingMode;
use Icso\Accounting\Utils\ManufacturingTrigger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BomRepo extends ElequentRepository
{
    protected $model;

    public function __construct(Bom $model)
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
                    $q->where('bom_name', 'like', '%' . $search . '%')
                        ->orWhere('bom_code', 'like', '%' . $search . '%')
                        ->orWhere('bom_version', 'like', '%' . $search . '%');
                });
            })
            ->when(!empty($where), function ($query) use ($where) {
                $query->where($where);
            })
            ->with(['product', 'outputUnit', 'items', 'items.product', 'items.unit'])
            ->orderBy('updated_at', 'desc');

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
                    $q->where('bom_name', 'like', '%' . $search . '%')
                        ->orWhere('bom_code', 'like', '%' . $search . '%')
                        ->orWhere('bom_version', 'like', '%' . $search . '%');
                });
            })
            ->when(!empty($where), function ($query) use ($where) {
                $query->where($where);
            })
            ->count();
    }

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;
        $arrData = [
            'bom_code' => !empty($request->bom_code) ? $request->bom_code : $this->generateBomCode($request->product_id),
            'bom_name' => $request->bom_name,
            'product_id' => $request->product_id,
            'output_unit_id' => $request->output_unit_id,
            'output_qty' => $request->output_qty,
            'bom_version' => !empty($request->bom_version) ? $request->bom_version : '1.0',
            'use_case' => !empty($request->use_case) ? $request->use_case : 'general',
            'manufacturing_mode' => !empty($request->manufacturing_mode) ? $request->manufacturing_mode : ManufacturingMode::PRE_PRODUCE,
            'auto_consume_trigger' => !empty($request->auto_consume_trigger) ? $request->auto_consume_trigger : ManufacturingTrigger::INVOICE,
            'status' => !empty($request->status) ? $request->status : 'active',
            'yield_percentage' => !empty($request->yield_percentage) ? $request->yield_percentage : 100,
            'note' => $request->note ?? null,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_by'] = $userId;
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $res = $this->create($arrData);
                $bomId = $res->id;
            } else {
                $this->update($arrData, $id);
                $bomId = $id;
                $this->deleteAdditional($id);
            }

            $items = $this->normalizeItems($request->items);
            foreach ($items as $index => $item) {
                BomItem::create([
                    'bom_id' => $bomId,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'qty' => $item->qty,
                    'waste_percentage' => $item->waste_percentage ?? 0,
                    'item_role' => $item->item_role ?? 'material',
                    'is_optional' => !empty($item->is_optional),
                    'sort_order' => $item->sort_order ?? ($index + 1),
                    'note' => $item->note ?? null,
                ]);
            }

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($id)
    {
        BomItem::where('bom_id', $id)->delete();
    }

    protected function normalizeItems($items): array
    {
        if (empty($items)) {
            return [];
        }

        if (is_array($items)) {
            return json_decode(json_encode($items));
        }

        return $items;
    }

    protected function generateBomCode($productId): string
    {
        $prefix = 'BOM';
        $nextId = ((int) Bom::max('id')) + 1;

        return $prefix . '-' . $productId . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }

    public function previewRequirements(int $bomId, float $outputQty, ?int $warehouseId = null, ?string $stockDate = null): array
    {
        $bom = Bom::with(['product', 'outputUnit', 'items', 'items.product', 'items.unit'])->find($bomId);
        if (empty($bom)) {
            return [];
        }

        $stockDate = !empty($stockDate) ? $stockDate : date('Y-m-d');
        $factor = $bom->output_qty > 0 ? ($outputQty / $bom->output_qty) : 1;
        $inventoryRepo = new InventoryRepo(new \Icso\Accounting\Models\Persediaan\Inventory());
        $materials = [];
        $estimatedMaterialCost = 0;

        foreach ($bom->items as $item) {
            $wasteFactor = 1 + (((float) $item->waste_percentage) / 100);
            $requiredQty = ((float) $item->qty) * $factor * $wasteFactor;
            $availableStock = $inventoryRepo->getStokByDate($item->product_id, $warehouseId ?: 0, $item->unit_id, $stockDate);
            $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $stockDate);
            $estimatedCost = $requiredQty * $hpp;
            $estimatedMaterialCost += $estimatedCost;

            $materials[] = [
                'bom_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product->item_name ?? '',
                'unit_id' => $item->unit_id,
                'unit_name' => $item->unit->unit_name ?? '',
                'qty_per_output' => (float) $item->qty,
                'required_qty' => $requiredQty,
                'available_stock' => $availableStock,
                'shortage_qty' => max(0, $requiredQty - $availableStock),
                'waste_percentage' => (float) $item->waste_percentage,
                'hpp' => $hpp,
                'estimated_cost' => $estimatedCost,
                'item_role' => $item->item_role,
                'is_optional' => (bool) $item->is_optional,
                'note' => $item->note,
            ];
        }

        $estimatedHppPerUnit = $outputQty > 0 ? ($estimatedMaterialCost / $outputQty) : 0;

        return [
            'bom' => $bom,
            'warehouse_id' => $warehouseId,
            'stock_date' => $stockDate,
            'output_qty' => $outputQty,
            'materials' => $materials,
            'summary' => [
                'material_count' => count($materials),
                'estimated_material_cost' => $estimatedMaterialCost,
                'estimated_hpp_per_unit' => $estimatedHppPerUnit,
            ],
        ];
    }
}
