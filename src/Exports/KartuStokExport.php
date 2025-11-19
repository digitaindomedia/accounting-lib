<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class KartuStokExport implements FromView
{
    protected $productId;
    protected $warehouseId;
    protected $fromDate;
    protected $untilDate;

    public function __construct($productId, $warehouseId, $fromDate, $untilDate)
    {
        $this->productId  = $productId;
        $this->warehouseId = $warehouseId;
        $this->fromDate    = $fromDate;
        $this->untilDate   = $untilDate;
    }

    public function view(): View
    {
        // Jika productId diisi → hanya 1 produk
        if ($this->productId) {
            $products = Product::where('id', $this->productId)->get();
        }
        else {
            // Jika kosong → semua item
            $products = Product::where('product_type', ProductType::ITEM)->get();
        }

        $result = [];

        foreach ($products as $product) {

            // ---- Saldo awal ----
            $saldoAwalQty = InventoryRepo::getStokBy(
                $product->id,
                $this->warehouseId,
                $this->fromDate,
                $this->untilDate,
                "<"
            )['total'];

            $saldoAwalNilai = InventoryRepo::getStokValueBy(
                $product->id,
                $this->warehouseId,
                $this->fromDate,
                $this->untilDate,
                "<"
            )['total'];

            // ---- Detail transaksi ----
            $where = ['product_id' => $product->id];
            if ($this->warehouseId) {
                $where[] = ['warehouse_id', '=', $this->warehouseId];
            }

            $inventories = Inventory::where($where)
                ->whereBetween('inventory_date', [$this->fromDate, $this->untilDate])
                ->orderBy('inventory_date', 'ASC')
                ->get();

            // saldo berjalan
            $runningQty   = $saldoAwalQty;
            $runningNilai = $saldoAwalNilai;

            foreach ($inventories as $inv) {
                $trx = TransactionsCode::getNumberAndNameTransaction(
                    $inv->transaction_code,
                    $inv->transaction_id
                );

                $inv->transaction_no   = $trx['transaction_no'];
                $inv->transaction_name = $trx['transaction_name'];

                $runningQty   += ($inv->qty_in - $inv->qty_out);
                $runningNilai += ($inv->total_in - $inv->total_out);

                $inv->saldo_qty   = $runningQty;
                $inv->saldo_nilai = $runningNilai;
            }

            $result[] = [
                'product' => $product,
                'saldo_awal_qty' => $saldoAwalQty,
                'saldo_awal_nilai' => $saldoAwalNilai,
                'details' => $inventories
            ];
        }

        return view('accounting::stock.kartu_stok_all_single_sheet', [
            'rows' => $result
        ]);
    }
}