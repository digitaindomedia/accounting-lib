<?php

namespace Icso\Accounting\Services;

use Exception;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Models\Manufacturing\Bom;
use Icso\Accounting\Models\Manufacturing\ProductionOrder;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Repositories\Manufacturing\Production\ProductionOrderRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\ManufacturingMode;
use Icso\Accounting\Utils\ManufacturingTrigger;
use Illuminate\Http\Request;

class AutoConsumeProductionService
{
    public function createFromSalesItems(array $items, array $context): void
    {
        foreach ($items as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            $qty = (float) ($row['qty'] ?? 0);
            $unitId = (int) ($row['unit_id'] ?? 0);

            if ($productId <= 0 || $qty <= 0 || $unitId <= 0) {
                continue;
            }

            $bom = $this->findApplicableBom($productId, $context['trigger']);
            if (empty($bom)) {
                continue;
            }

            $productionRepo = new ProductionOrderRepo(new ProductionOrder());
            $request = new Request();
            $request->user_id = $context['user_id'];
            $request->production_date = $context['transaction_date'];
            $request->warehouse_id = $context['warehouse_id'];
            $request->bom_id = $bom->id;
            $request->product_id = $productId;
            $request->output_unit_id = $bom->output_unit_id ?: $unitId;
            $request->planned_qty = $qty;
            $request->actual_qty = $qty;
            $request->status_production = 'finished';
            $request->source_type = $context['source_type'];
            $request->source_id = $context['source_id'];
            $request->coa_id = $this->resolveProductionCoa($productId);
            $request->note = $context['note'];
            $request->reason = 'auto_consume';
            $res = $productionRepo->store($request);
            if (!$res) {
                throw new Exception("Auto consume produksi gagal untuk product_id {$productId}");
            }
        }
    }

    public function deleteBySource(string $sourceType, int $sourceId): void
    {
        $rows = ProductionOrder::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $productionRepo = new ProductionOrderRepo(new ProductionOrder());
        foreach ($rows as $row) {
            $productionRepo->deleteAdditional($row->id);
            $productionRepo->delete($row->id);
        }
    }

    protected function findApplicableBom(int $productId, string $trigger): ?Bom
    {
        return Bom::where('product_id', $productId)
            ->where('status', 'active')
            ->whereIn('manufacturing_mode', [ManufacturingMode::AUTO_CONSUME, ManufacturingMode::BOTH])
            ->whereIn('auto_consume_trigger', [$trigger, ManufacturingTrigger::BOTH])
            ->orderBy('updated_at', 'desc')
            ->first();
    }

    protected function resolveProductionCoa(int $productId): int
    {
        $coaId = (int) SettingRepo::getOptionValue(SettingEnum::COA_PRODUKSI_WIP);
        if ($coaId > 0) {
            return $coaId;
        }

        $product = Product::find($productId);
        return (int) ($product->coa_id ?? 0);
    }
}
