<?php

namespace Icso\Accounting\Repositories\Persediaan\Inventory\Interface;

use Icso\Accounting\Models\Akuntansi\Jurnal;
use Icso\Accounting\Models\Manufacturing\ProductionOrder;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseRetur;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\Retur\SalesRetur;
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\Mutation;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Models\Persediaan\StockUsage;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Manufacturing\Production\ProductionOrderRepo;
use Icso\Accounting\Repositories\Pembelian\Retur\ReturRepo as PurchaseReturRepo;
use Icso\Accounting\Repositories\Penjualan\Retur\ReturRepo as SalesReturRepo;
use Icso\Accounting\Repositories\Persediaan\Mutation\MutationRepo;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

trait RebuildsInventory
{
    // Posting adapters create their own InventoryRepo instances in the same process.
    private static ?string $rebuildCreatedAt = null;
    private static bool $rebuildInProgress = false;
    private static array $rebuildWarnings = [];

    /** Replay source documents in effective-date / original-input order, atomically. */
    public function recalculateStock(?string $actorId = null): array
    {
        $connection = DB::connection();
        $mysql = $connection->getDriverName() === 'mysql';
        $lockName = 'inventory-rebuild:' . substr(hash('sha256', $connection->getDatabaseName()), 0, 40);
        if ($mysql) {
            $isolation = strtoupper((string) $connection->selectOne('SELECT @@transaction_isolation AS level')->level);
            if (!in_array($isolation, ['REPEATABLE-READ', 'SERIALIZABLE'], true)) {
                throw new \RuntimeException('Rebuild memerlukan isolation REPEATABLE READ atau SERIALIZABLE untuk mengunci sumber transaksi.');
            }
        }
        if ($mysql && (int) $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])->acquired !== 1) {
            throw new \RuntimeException('Hitung ulang persediaan tenant ini sedang berjalan.');
        }

        try {
            self::$rebuildInProgress = true;
            self::$rebuildWarnings = [];
            return $connection->transaction(function () use ($actorId, $connection) {
                $sources = $this->inventoryRebuildSources();
                $this->lockInventorySources($sources);
                $codes = array_column($sources, 'code');
                $codes[] = TransactionsCode::PRODUCTION_RESULT;
                $unknown = Inventory::whereNotIn('transaction_code', $codes)->value('transaction_code');
                if ($unknown !== null) {
                    throw new \RuntimeException("Sumber persediaan belum didukung: {$unknown}. Rebuild dibatalkan.");
                }

                $events = [];
                $summary = array_fill_keys(array_keys($sources), 0);
                foreach ($sources as $kind => $source) {
                    $query = $source['model']::query();
                    if ($kind === 'produksi') {
                        $query->where('status_production', 'finished');
                    }
                    foreach ($query->select(['id', $source['date'], 'created_at'])->orderBy('id')->cursor() as $row) {
                        $date = (string) $row->{$source['date']};
                        if (!$date) {
                            throw new \RuntimeException("Tanggal {$kind} #{$row->id} kosong.");
                        }
                        $events[] = [
                            'kind' => $kind, 'id' => $row->id,
                            'date' => substr($date, 0, 10),
                            'created_at' => (string) ($row->created_at ?: substr($date, 0, 10) . ' 00:00:00'),
                            'priority' => $source['priority'],
                        ];
                    }
                }
                $events = self::sortInventoryEvents($events);
                // Keep FK checks enabled. Any failure rolls back inventory, journals and source HPP.
                $connection->table(Inventory::getTableName())->delete();
                foreach ($events as $event) {
                    self::$rebuildCreatedAt = $event['created_at'];
                    $summary[$event['kind']] += $this->replayInventoryEvent($event, $actorId);
                }
                self::$rebuildCreatedAt = null;
                $journalSummary = $this->repostAccountingJournals();
                foreach (['adjustment', 'pemakaian', 'retur_pembelian', 'retur_penjualan', 'mutasi', 'produksi'] as $kind) {
                    $journalSummary[$kind] = count(array_filter($events, static fn ($event) => $event['kind'] === $kind));
                }
                return [
                    'status' => true, 'summary' => $summary,
                    'journal_summary' => $journalSummary,
                    'total' => Inventory::count(),
                    'warnings' => self::$rebuildWarnings,
                ];
            });
        } finally {
            self::$rebuildInProgress = false;
            self::$rebuildWarnings = [];
            self::$rebuildCreatedAt = null;
            if ($mysql) {
                $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            }
        }
    }

    private function inventoryRebuildSources(): array
    {
        return [
            'saldo_awal' => ['model' => StockAwal::class, 'date' => 'stock_date', 'code' => TransactionsCode::SALDO_AWAL, 'priority' => 0],
            'penerimaan' => ['model' => PurchaseReceived::class, 'date' => 'receive_date', 'code' => TransactionsCode::PENERIMAAN, 'priority' => 10],
            'invoice_pembelian_tanpa_penerimaan' => ['model' => PurchaseInvoicing::class, 'date' => 'invoice_date', 'code' => TransactionsCode::INVOICE_PEMBELIAN, 'priority' => 20],
            'adjustment' => ['model' => Adjustment::class, 'date' => 'adjustment_date', 'code' => TransactionsCode::ADJUSTMENT, 'priority' => 30],
            'pemakaian' => ['model' => StockUsage::class, 'date' => 'usage_date', 'code' => TransactionsCode::PEMAKAIAN_STOCK, 'priority' => 40],
            'pengiriman' => ['model' => SalesDelivery::class, 'date' => 'delivery_date', 'code' => TransactionsCode::DELIVERY_ORDER, 'priority' => 50],
            'invoice_penjualan_tanpa_pengiriman' => ['model' => SalesInvoicing::class, 'date' => 'invoice_date', 'code' => TransactionsCode::INVOICE_PENJUALAN, 'priority' => 60],
            'retur_pembelian' => ['model' => PurchaseRetur::class, 'date' => 'retur_date', 'code' => TransactionsCode::RETUR_PEMBELIAN, 'priority' => 70],
            'retur_penjualan' => ['model' => SalesRetur::class, 'date' => 'retur_date', 'code' => TransactionsCode::RETUR_PENJUALAN, 'priority' => 80],
            'mutasi' => ['model' => Mutation::class, 'date' => 'mutation_date', 'code' => TransactionsCode::MUTATION, 'priority' => 90],
            'produksi' => ['model' => ProductionOrder::class, 'date' => 'production_date', 'code' => TransactionsCode::PRODUCTION_MATERIAL, 'priority' => 100],
            'jurnal' => ['model' => Jurnal::class, 'date' => 'jurnal_date', 'code' => TransactionsCode::JURNAL, 'priority' => 110],
        ];
    }

    private static function sortInventoryEvents(array $events): array
    {
        usort($events, static fn (array $a, array $b) =>
            [$a['date'], $a['created_at'], $a['priority'], $a['id']]
            <=> [$b['date'], $b['created_at'], $b['priority'], $b['id']]);
        return $events;
    }

    private function lockInventorySources(array $sources): void
    {
        $tables = array_map(static fn ($source) => (new $source['model'])->getTable(), $sources);
        $tables = array_merge($tables, [
            'als_inventory', 'als_product', 'als_product_convertion', 'als_jurnal_transactions',
            'als_purchase_receive_product', 'als_purchase_order_product', 'als_purchase_invoice_receive',
            'als_sales_delivery_product', 'als_sales_order_product', 'als_sales_invoice_delivery',
            'als_purchase_retur_product', 'als_sales_retur_product',
            'als_warehouse_mutation_product', 'als_jurnal_akun',
            (new \Icso\Accounting\Models\Persediaan\AdjustmentProducts())->getTable(),
            (new \Icso\Accounting\Models\Persediaan\StockUsageProduct())->getTable(),
            (new \Icso\Accounting\Models\Manufacturing\ProductionOrderMaterial())->getTable(),
            (new \Icso\Accounting\Models\Manufacturing\ProductionOrderResult())->getTable(),
        ]);
        sort($tables);
        foreach (array_unique($tables) as $table) {
            // Lock source rows before taking the snapshot; detail/header edits must wait.
            DB::table($table)->select('id')->orderBy('id')->lockForUpdate()
                ->chunk(1000, static function ($rows) {});
        }
    }

    private function replayInventoryEvent(array $event, ?string $actorId): int
    {
        $id = $event['id'];
        return match ($event['kind']) {
            'saldo_awal' => $this->rebuildFromStockAwal($actorId, $id),
            'penerimaan' => $this->rebuildFromPurchaseReceive($actorId, $id),
            'invoice_pembelian_tanpa_penerimaan' => $this->rebuildFromDirectPurchaseInvoice($actorId, $id),
            'adjustment' => $this->rebuildFromAdjustment($id),
            'pemakaian' => $this->rebuildFromStockUsage($id),
            'pengiriman' => $this->rebuildFromSalesDelivery($actorId, $id),
            'invoice_penjualan_tanpa_pengiriman' => $this->rebuildFromDirectSalesInvoice($actorId, $id),
            'retur_pembelian' => $this->rebuildPurchaseReturn($id, $actorId),
            'retur_penjualan' => $this->rebuildSalesReturn($id),
            'mutasi' => $this->rebuildMutation($id),
            'produksi' => $this->rebuildProduction($id),
            'jurnal' => $this->rebuildJournalInventory($id, $actorId),
        };
    }

    private function rebuildPurchaseReturn($id, ?string $actorId): int
    {
        $return = PurchaseRetur::with(['returproduct' => fn ($q) => $q->orderBy('id'), 'returproduct.product', 'receive', 'invoice'])->findOrFail($id);
        foreach ($return->returproduct as $item) {
            $this->store(new Request([
                'inventory_date' => $return->retur_date,
                'transaction_code' => TransactionsCode::RETUR_PEMBELIAN,
                'transaction_id' => $id, 'transaction_sub_id' => $item->id,
                'product_id' => $item->product_id, 'unit_id' => $item->unit_id,
                'warehouse_id' => $return->warehouse_id ?: ($return->receive?->warehouse_id ?: $return->invoice?->warehouse_id), 'coa_id' => $item->product?->coa_id ?? 0,
                'qty_out' => $item->qty, 'price' => $item->hpp_price,
                'note' => $return->note, 'user_id' => $this->resolveActorId($actorId, $return->created_by),
            ]));
        }
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::RETUR_PEMBELIAN, $id);
        (new PurchaseReturRepo(new PurchaseRetur(), app(ActivityLogService::class)))->postingJurnal($id);
        return $return->returproduct->count();
    }

    private function rebuildSalesReturn($id): int
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::RETUR_PENJUALAN, $id);
        (new SalesReturRepo(new SalesRetur(), app(ActivityLogService::class)))->postingJurnal($id);
        return Inventory::where('transaction_code', TransactionsCode::RETUR_PENJUALAN)->where('transaction_id', $id)->count();
    }

    private function rebuildMutation($id): int
    {
        (new MutationRepo(new Mutation(), app(ActivityLogService::class)))->postingJurnal($id, true);
        return Inventory::where('transaction_code', TransactionsCode::MUTATION)->where('transaction_id', $id)->count();
    }

    private function rebuildProduction($id): int
    {
        (new ProductionOrderRepo(new ProductionOrder()))->repostInventoryAndJournal($id);
        return Inventory::whereIn('transaction_code', [TransactionsCode::PRODUCTION_MATERIAL, TransactionsCode::PRODUCTION_RESULT])
            ->where('transaction_id', $id)->count();
    }

    private function rebuildJournalInventory($id, ?string $actorId): int
    {
        $journal = Jurnal::with(['jurnal_akun' => fn ($q) => $q->orderBy('id')])->findOrFail($id);
        $count = 0;
        foreach ($journal->jurnal_akun as $line) {
            if (empty($line->data_sess)) {
                continue;
            }
            $session = json_decode($line->data_sess, false, 512, JSON_THROW_ON_ERROR);
            if (($session->var_kontak ?? '') !== 'persediaan') {
                continue;
            }
            if (empty($session->var_barang->id) || empty($session->var_barang->unit_id) || empty($session->var_warehouse->id)) {
                throw new \RuntimeException("Detail persediaan jurnal {$id}/{$line->id} tidak lengkap.");
            }
            $incoming = $session->var_type == VarType::PENAMBAHAN;
            $this->store(new Request([
                'inventory_date' => $journal->jurnal_date,
                'transaction_code' => TransactionsCode::JURNAL,
                'transaction_id' => $id, 'transaction_sub_id' => $line->id,
                'product_id' => $session->var_barang->id, 'unit_id' => $session->var_barang->unit_id,
                'warehouse_id' => $session->var_warehouse->id, 'coa_id' => $line->coa_id,
                'qty_in' => $incoming ? $session->var_qty : 0,
                'qty_out' => $incoming ? 0 : $session->var_qty,
                'price' => $session->var_hpp, 'note' => $session->var_note_ref ?? '',
                'user_id' => $this->resolveActorId($actorId, $journal->created_by),
            ]));
            $count++;
        }
        // General journal values are explicit user input; do not recreate unrelated payments.
        return $count;
    }
}
