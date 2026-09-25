<?php

namespace Icso\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductUnitGuard
{
    // Include unposted documents too: their selected units must remain meaningful.
    private const TABLES = [
        'als_stock_awal', 'als_inventory', 'als_purchase_request_product',
        'als_purchase_order_product', 'als_purchase_receive_product', 'als_purchase_bast_product',
        'als_purchase_retur_product', 'als_sales_quotation_product', 'als_sales_order_product',
        'als_sales_spk_product', 'als_sales_delivery_product', 'als_sales_retur_product',
        'als_adjustment_product', 'als_stock_usage_product', 'als_warehouse_mutation_product',
        'als_production_order', 'als_production_order_material', 'als_production_order_result',
        'als_jurnal_akun',
    ];

    public function usedUnits($productId): array
    {
        $db = DB::connection();
        $schema = $db->getSchemaBuilder();
        $units = [];
        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'product_id')) {
                continue;
            }
            $query = $db->table($table)->where('product_id', $productId);
            if ($schema->hasColumn($table, 'unit_id')) {
                foreach ($query->distinct()->pluck('unit_id') as $unit) {
                    $units[(string) $unit] = $unit;
                }
            } elseif ($query->exists()) {
                $units[''] = null;
            }
        }
        return array_values($units);
    }

    public function assertBaseUnitUnchanged($productId, $oldUnit, $newUnit): void
    {
        if ((string) $oldUnit !== (string) $newUnit && $this->usedUnits($productId) !== []) {
            throw ValidationException::withMessages([
                'unit_id' => 'Satuan dasar tidak dapat diubah karena produk sudah digunakan dalam transaksi.',
            ]);
        }
    }

    public function prepareConversions($productId, $baseUnit, array $oldRows, array $newRows): array
    {
        $new = [];
        foreach ($newRows as $row) {
            $row = (array) $row;
            $unit = (string) ($row['unit_id'] ?? '');
            $factor = $row['nilai'] ?? null;
            if (!$unit || $unit === (string) $baseUnit || isset($new[$unit]) || !is_numeric($factor)
                || !is_finite((float) $factor) || (float) $factor <= 0 || empty($row['base_unit_id'])) {
                $this->invalid('Daftar konversi satuan tidak valid.');
            }
            $new[$unit] = [
                'product_id' => $productId, 'unit_id' => $row['unit_id'],
                'base_unit_id' => $row['base_unit_id'], 'nilai' => (float) $factor, 'price' => 0,
            ];
        }
        foreach ($new as $unit => &$row) {
            $factor = 1.0;
            $cursor = (string) $unit;
            $seen = [];
            while ($cursor !== (string) $baseUnit) {
                if (isset($seen[$cursor]) || !isset($new[$cursor])) {
                    $this->invalid('Konversi harus menuju satuan dasar produk tanpa siklus.');
                }
                $seen[$cursor] = true;
                $factor *= $new[$cursor]['nilai'];
                $cursor = (string) $new[$cursor]['base_unit_id'];
            }
            if (!is_finite($factor) || $factor <= 0) {
                $this->invalid('Faktor konversi satuan tidak valid.');
            }
            $row['nilai_terkecil'] = $factor;
        }
        unset($row);

        $old = [];
        foreach ($oldRows as $row) {
            $row = (array) $row;
            $old[(string) $row['unit_id']] = $row;
        }
        // Protect the entire old chain when a transaction uses a dependent unit.
        $protected = [];
        foreach ($this->usedUnits($productId) as $unit) {
            $cursor = (string) $unit;
            while (isset($old[$cursor]) && !isset($protected[$cursor])) {
                $protected[$cursor] = true;
                $cursor = (string) $old[$cursor]['base_unit_id'];
            }
        }
        foreach ($protected as $unit => $_) {
            $before = $old[$unit];
            $after = $new[$unit] ?? null;
            if (!$after || (string) $before['base_unit_id'] !== (string) $after['base_unit_id']
                || (float) $before['nilai'] !== (float) $after['nilai']
                || abs((float) $before['nilai_terkecil'] - $after['nilai_terkecil']) > 0.00000001) {
                $this->invalid("Konversi satuan {$unit} tidak dapat dihapus atau diubah karena sudah digunakan dalam transaksi.");
            }
        }
        return array_values($new);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['product_convertion' => $message]);
    }
}
