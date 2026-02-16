<?php

namespace Icso\Accounting\Services;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedProductItem;
use Exception;
use Illuminate\Support\Facades\DB;
class IdentityStockService
{
    /**
     * Kurangi stok identity (OUT)
     * Dipakai oleh:
     * - Delivery Order
     * - Retur Pembelian
     */
    public function consume(array $items): void
    {
        foreach ($items as $row) {

            $identity = PurchaseReceivedProductItem::lockForUpdate()
                ->find($row['identity_item_id']);

            if (!$identity || $identity->status !== 'OPEN') {
                throw new Exception("Identity tidak tersedia");
            }

            if ($row['qty'] > $identity->qty_left) {
                throw new Exception("Qty melebihi stok identity");
            }

            $identity->qty_left -= $row['qty'];

            if ($identity->qty_left <= 0) {
                $identity->status = StatusEnum::CLOSE;
            }

            $identity->save();
        }
    }

    /**
     * Kembalikan stok identity (IN)
     * Dipakai oleh:
     * - Retur Penjualan
     */
    public function restore(array $items): void
    {
        foreach ($items as $row) {

            $identity = PurchaseReceivedProductItem::lockForUpdate()
                ->find($row['identity_item_id']);

            if (!$identity) {
                throw new Exception("Identity tidak ditemukan");
            }

            $identity->qty_left += $row['qty'];
            $identity->status = 'OPEN';

            $identity->save();
        }
    }

    /**
     * Buat identity baru (opsional)
     * Dipakai jika retur penjualan menghasilkan identity baru
     */
    public function create(array $data): PurchaseReceivedProductItem
    {
        return PurchaseReceivedProductItem::create([
            'receive_product_id' => $data['receive_product_id'] ?? null,
            'product_id'         => $data['product_id'],
            'warehouse_id'       => $data['warehouse_id'],
            'identity_value'     => $data['identity_value'],
            'qty'                => $data['qty'],
            'qty_left'           => $data['qty'],
            'status'             => StatusEnum::OPEN,
        ]);
    }
}