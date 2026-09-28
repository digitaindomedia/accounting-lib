<?php

namespace Icso\Accounting\Exports;

use Generator;
use Icso\Accounting\Models\Akuntansi\Jurnal;
use Icso\Accounting\Models\Manufacturing\ProductionOrder;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseRetur;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\Retur\SalesRetur;
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\Mutation;
use Icso\Accounting\Models\Persediaan\StockUsage;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KartuStokExport implements FromGenerator, WithColumnFormatting, WithColumnWidths, WithStyles
{
    private const PRODUCT_BATCH_SIZE = 50;
    private int $currentRow = 0;
    private array $productTitleRows = [];
    private array $tableHeaderRows = [];
    private array $openingBalanceRows = [];

    public function __construct(
        private readonly ?string $search,
        private readonly mixed $productId,
        private readonly mixed $warehouseId,
        private readonly string $fromDate,
        private readonly string $untilDate,
    ) {
    }

    /** Generates rows in bounded batches instead of rendering the whole report in memory. */
    public function generator(): Generator
    {
        $this->currentRow = 0;
        $this->productTitleRows = [];
        $this->tableHeaderRows = [];
        $this->openingBalanceRows = [];

        yield $this->nextRow(['KARTU STOK']);
        yield $this->nextRow([$this->periodLabel()]);
        $lastProductId = 0;

        while (true) {
            $products = $this->productQuery()->where('id', '>', $lastProductId)
                ->orderBy('id')->limit(self::PRODUCT_BATCH_SIZE)->get();
            if ($products->isEmpty()) {
                return;
            }

            $lastProductId = (int) $products->last()->id;
            $productIds = $products->pluck('id')->all();
            $openingBalances = $this->openingBalances($productIds);
            $movementsByProduct = $this->movements($productIds)->groupBy('product_id');

            foreach ($products as $product) {
                $opening = $openingBalances->get($product->id);
                $saldoQty = (float) ($opening->saldo_qty ?? 0);
                $saldoNilai = (float) ($opening->saldo_nilai ?? 0);

                yield $this->nextRow([]);
                $this->productTitleRows[] = $this->currentRow + 1;
                yield $this->nextRow(['Nama Produk: ' . $product->item_name . ' - ' . $product->item_code]);
                $this->tableHeaderRows[] = $this->currentRow + 1;
                yield $this->nextRow(['Tanggal', 'Nomor', 'Transaksi', 'Qty Masuk', 'Nilai Masuk', 'Qty Keluar', 'Nilai Keluar', 'Saldo Qty', 'Saldo Nilai']);
                $this->openingBalanceRows[] = $this->currentRow + 1;
                yield $this->nextRow(['Saldo Awal', '', '', '', '', '', '', $saldoQty, $saldoNilai]);

                foreach ($movementsByProduct->get($product->id, collect()) as $movement) {
                    $saldoQty += (float) $movement->qty_in - (float) $movement->qty_out;
                    $saldoNilai += (float) $movement->total_in - (float) $movement->total_out;
                    yield $this->nextRow([
                        $movement->inventory_date, $movement->transaction_no, $movement->transaction_name,
                        (float) $movement->qty_in, (float) $movement->total_in,
                        (float) $movement->qty_out, (float) $movement->total_out, $saldoQty, $saldoNilai,
                    ]);
                }
            }
        }
    }

    public function columnFormats(): array
    {
        return ['D:I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 20,
            'C' => 28,
            'D' => 14,
            'E' => 17,
            'F' => 14,
            'G' => 17,
            'H' => 15,
            'I' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A1:I2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setOrientation('landscape');

        $styles = [
            'A1:I1' => [
                'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '1F4E78']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            'A2:I2' => [
                'font' => ['italic' => true, 'color' => ['rgb' => '404040']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];

        foreach ($this->productTitleRows as $row) {
            $sheet->mergeCells('A' . $row . ':I' . $row);
            $styles['A' . $row . ':I' . $row] = [
                'font' => ['bold' => true, 'color' => ['rgb' => '1F1F1F']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'D9EAF7']],
            ];
        }
        foreach ($this->tableHeaderRows as $row) {
            $styles[$row] = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => '4472C4']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];
        }
        foreach ($this->openingBalanceRows as $row) {
            $styles['A' . $row . ':I' . $row] = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'color' => ['rgb' => 'E2F0D9']],
            ];
        }

        return $styles;
    }

    private function nextRow(array $values): array
    {
        $this->currentRow++;

        return $values;
    }

    private function periodLabel(): string
    {
        $fromDate = date('d/m/Y', strtotime($this->fromDate));
        $untilDate = date('d/m/Y', strtotime($this->untilDate));

        return $this->fromDate === $this->untilDate
            ? 'Tanggal: ' . $fromDate
            : 'Periode: ' . $fromDate . ' s.d. ' . $untilDate;
    }

    private function productQuery()
    {
        return Product::query()->select(['id', 'item_name', 'item_code'])->where('product_type', ProductType::ITEM)
            ->when($this->productId, fn ($query) => $query->where('id', $this->productId))
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('item_name', 'like', '%' . $this->search . '%')
                        ->orWhere('item_code', 'like', '%' . $this->search . '%')
                        ->orWhere('descriptions', 'like', '%' . $this->search . '%');
                });
            });
    }

    private function openingBalances(array $productIds): Collection
    {
        return Inventory::query()->select('product_id')
            ->selectRaw('COALESCE(SUM(qty_in), 0) - COALESCE(SUM(qty_out), 0) AS saldo_qty')
            ->selectRaw('COALESCE(SUM(total_in), 0) - COALESCE(SUM(total_out), 0) AS saldo_nilai')
            ->whereIn('product_id', $productIds)->where('inventory_date', '<', $this->fromDate)
            ->when($this->warehouseId, fn ($query) => $query->where('warehouse_id', $this->warehouseId))
            ->groupBy('product_id')->get()->keyBy('product_id');
    }

    private function movements(array $productIds): Collection
    {
        $movements = Inventory::query()->whereIn('product_id', $productIds)
            ->whereBetween('inventory_date', [$this->fromDate, $this->untilDate])
            ->when($this->warehouseId, fn ($query) => $query->where('warehouse_id', $this->warehouseId))
            ->orderBy('product_id')->orderBy('inventory_date')->orderBy('id')->get();
        $references = $this->transactionReferences($movements);

        foreach ($movements as $movement) {
            $reference = $references[$this->referenceKey($movement->transaction_code, $movement->transaction_id)] ?? [];
            $movement->transaction_name = $reference['transaction_name'] ?? '';
            $movement->transaction_no = $reference['transaction_no'] ?? '';
        }

        return $movements;
    }

    private function transactionReferences(Collection $movements): array
    {
        $references = [];
        $map = $this->transactionMap();

        foreach ($movements->groupBy('transaction_code') as $code => $items) {
            $config = $map[$code] ?? null;
            if ($config === null) {
                continue;
            }
            $ids = $items->pluck('transaction_id')->filter()->unique()->values();
            $numbers = $config['number_column'] === null ? []
                : $config['model']::whereIn('id', $ids)->pluck($config['number_column'], 'id')->all();

            foreach ($ids as $id) {
                $references[$this->referenceKey($code, $id)] = [
                    'transaction_name' => $config['name'],
                    'transaction_no' => $numbers[$id] ?? '',
                ];
            }
        }

        return $references;
    }

    private function transactionMap(): array
    {
        return [
            TransactionsCode::SALDO_AWAL => ['model' => null, 'number_column' => null, 'name' => 'SALDO AWAL'],
            TransactionsCode::JURNAL => ['model' => Jurnal::class, 'number_column' => 'jurnal_no', 'name' => 'JURNAL'],
            TransactionsCode::PENERIMAAN => ['model' => PurchaseReceived::class, 'number_column' => 'receive_no', 'name' => 'PENERIMAAN PEMBELIAN'],
            TransactionsCode::INVOICE_PEMBELIAN => ['model' => PurchaseInvoicing::class, 'number_column' => 'invoice_no', 'name' => 'INVOICE PEMBELIAN'],
            TransactionsCode::RETUR_PEMBELIAN => ['model' => PurchaseRetur::class, 'number_column' => 'retur_no', 'name' => 'RETUR PEMBELIAN'],
            TransactionsCode::DELIVERY_ORDER => ['model' => SalesDelivery::class, 'number_column' => 'delivery_no', 'name' => 'PENGIRIMAN PENJUALAN'],
            TransactionsCode::INVOICE_PENJUALAN => ['model' => SalesInvoicing::class, 'number_column' => 'invoice_no', 'name' => 'INVOICE PENJUALAN'],
            TransactionsCode::RETUR_PENJUALAN => ['model' => SalesRetur::class, 'number_column' => 'retur_no', 'name' => 'RETUR PENJUALAN'],
            TransactionsCode::ADJUSTMENT => ['model' => Adjustment::class, 'number_column' => 'ref_no', 'name' => 'PENYESUAIAN STOK'],
            TransactionsCode::MUTATION => ['model' => Mutation::class, 'number_column' => 'ref_no', 'name' => 'MUTASI GUDANG'],
            TransactionsCode::PEMAKAIAN_STOCK => ['model' => StockUsage::class, 'number_column' => 'ref_no', 'name' => 'PEMAKAIAN STOK'],
            TransactionsCode::PRODUCTION_MATERIAL => ['model' => ProductionOrder::class, 'number_column' => 'ref_no', 'name' => 'PRODUKSI BAHAN BAKU'],
            TransactionsCode::PRODUCTION_RESULT => ['model' => ProductionOrder::class, 'number_column' => 'ref_no', 'name' => 'HASIL PRODUKSI'],
        ];
    }

    private function referenceKey(string $transactionCode, mixed $transactionId): string
    {
        return $transactionCode . ':' . $transactionId;
    }
}
