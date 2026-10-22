<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Utils\ProductType;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class KartuStokRekapExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, ShouldAutoSize, WithStrictNullComparison
{
    public function __construct(
        private readonly ?string $search,
        private readonly mixed $productId,
        private readonly mixed $warehouseId,
        private readonly string $fromDate,
        private readonly string $untilDate,
    ) {
    }

    public function collection(): Collection
    {
        $productTable = Product::getTableName();

        $openingBalances = Inventory::query()
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(qty_in), 0) - COALESCE(SUM(qty_out), 0) AS saldo_awal')
            ->where('inventory_date', '<', $this->fromDate)
            ->when($this->warehouseId, fn ($query) => $query->where('warehouse_id', $this->warehouseId))
            ->groupBy('product_id');

        $movements = Inventory::query()
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(qty_in), 0) AS qty_in')
            ->selectRaw('COALESCE(SUM(qty_out), 0) AS qty_out')
            ->whereBetween('inventory_date', [$this->fromDate, $this->untilDate])
            ->when($this->warehouseId, fn ($query) => $query->where('warehouse_id', $this->warehouseId))
            ->groupBy('product_id');

        return Product::query()
            ->leftJoinSub($openingBalances, 'opening_balance', function ($join) use ($productTable) {
                $join->on('opening_balance.product_id', '=', $productTable . '.id');
            })
            ->leftJoinSub($movements, 'period_movement', function ($join) use ($productTable) {
                $join->on('period_movement.product_id', '=', $productTable . '.id');
            })
            ->select([
                $productTable . '.item_name as product_name',
                $productTable . '.item_code as product_code',
            ])
            ->selectRaw('COALESCE(opening_balance.saldo_awal, 0) AS saldo_awal')
            ->selectRaw('COALESCE(period_movement.qty_in, 0) AS qty_in')
            ->selectRaw('COALESCE(period_movement.qty_out, 0) AS qty_out')
            ->selectRaw('COALESCE(opening_balance.saldo_awal, 0) + COALESCE(period_movement.qty_in, 0) - COALESCE(period_movement.qty_out, 0) AS saldo_akhir')
            ->where($productTable . '.product_type', ProductType::ITEM)
            ->when($this->productId, fn ($query) => $query->where($productTable . '.id', $this->productId))
            ->when($this->search, function ($query) use ($productTable) {
                $query->where(function ($query) use ($productTable) {
                    $query->where($productTable . '.item_name', 'like', '%' . $this->search . '%')
                        ->orWhere($productTable . '.item_code', 'like', '%' . $this->search . '%')
                        ->orWhere($productTable . '.descriptions', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy($productTable . '.item_name')
            ->get();
    }

    public function headings(): array
    {
        return ['Nama Produk', 'Kode Produk', 'Saldo Awal', 'Qty Masuk', 'Qty Keluar', 'Saldo Akhir'];
    }

    public function map($row): array
    {
        return [
            $row->product_name,
            $row->product_code,
            (float) $row->saldo_awal,
            (float) $row->qty_in,
            (float) $row->qty_out,
            (float) $row->saldo_akhir,
        ];
    }

    public function columnFormats(): array
    {
        return ['C:F' => '#,##0.00;-#,##0.00;0.00'];
    }
}
