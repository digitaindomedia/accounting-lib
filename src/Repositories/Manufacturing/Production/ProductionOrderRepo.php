<?php

namespace Icso\Accounting\Repositories\Manufacturing\Production;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Manufacturing\Bom;
use Icso\Accounting\Models\Manufacturing\ProductionOrder;
use Icso\Accounting\Models\Manufacturing\ProductionOrderMaterial;
use Icso\Accounting\Models\Manufacturing\ProductionOrderResult;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionOrderRepo extends ElequentRepository
{
    protected $model;

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

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;
        $productionDate = !empty($request->production_date)
            ? Utility::changeDateFormat($request->production_date)
            : date('Y-m-d');
        $plannedQty = (float) $request->planned_qty;
        $actualQty = !empty($request->actual_qty) ? (float) $request->actual_qty : $plannedQty;

        $arrData = [
            'ref_no' => !empty($request->ref_no) ? $request->ref_no : $this->generateRefNo(),
            'production_date' => $productionDate,
            'warehouse_id' => $request->warehouse_id,
            'bom_id' => !empty($request->bom_id) ? $request->bom_id : null,
            'product_id' => $request->product_id,
            'output_unit_id' => $request->output_unit_id,
            'planned_qty' => $plannedQty,
            'actual_qty' => $actualQty,
            'status_production' => !empty($request->status_production) ? $request->status_production : 'finished',
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

            $materialRows = [];
            foreach ($materials as $item) {
                $materialRows[] = ProductionOrderMaterial::create([
                    'production_order_id' => $productionId,
                    'bom_item_id' => $item->bom_item_id ?? 0,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'qty_planned' => !empty($item->qty_planned) ? $item->qty_planned : 0,
                    'qty_actual' => !empty($item->qty_actual) ? $item->qty_actual : (!empty($item->qty_planned) ? $item->qty_planned : 0),
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
                    'qty_good' => !empty($item->qty_good) ? $item->qty_good : 0,
                    'qty_waste' => !empty($item->qty_waste) ? $item->qty_waste : 0,
                    'hpp' => 0,
                    'subtotal' => 0,
                    'result_role' => $item->result_role ?? 'main',
                    'note' => $item->note ?? null,
                ]);
            }

            $this->postingInventoryAndJournal($productionId, $materialRows, $resultRows);

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
        ProductionOrderMaterial::where('production_order_id', $id)->delete();
        ProductionOrderResult::where('production_order_id', $id)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PRODUCTION_MATERIAL, $id);
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PRODUCTION_RESULT, $id);
        Inventory::where('transaction_code', TransactionsCode::PRODUCTION_MATERIAL)->where('transaction_id', $id)->delete();
        Inventory::where('transaction_code', TransactionsCode::PRODUCTION_RESULT)->where('transaction_id', $id)->delete();
    }

    protected function resolveMaterials(Request $request, float $plannedQty, float $actualQty): array
    {
        if (!empty($request->materials)) {
            return $this->normalizeArrayInput($request->materials);
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
            $rows[] = (object) [
                'bom_item_id' => $item->id,
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'qty_planned' => ((float) $item->qty) * $plannedFactor * $wasteFactor,
                'qty_actual' => ((float) $item->qty) * $actualFactor * $wasteFactor,
                'line_type' => $item->item_role ?: 'material',
                'note' => $item->note,
            ];
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

        $resultHpp = $totalGoodQty > 0 ? $totalMaterialValue / $totalGoodQty : 0;

        foreach ($resultRows as $item) {
            $qtyGood = (float) $item->qty_good;
            $subtotal = $resultHpp * $qtyGood;
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

    protected function generateRefNo(): string
    {
        $nextId = ((int) ProductionOrder::max('id')) + 1;
        return 'PROD-' . date('Ymd') . '-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }
}
