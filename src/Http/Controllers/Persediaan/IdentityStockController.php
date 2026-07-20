<?php

namespace Icso\Accounting\Http\Controllers\Persediaan;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedProductItem;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdentityStockController extends Controller
{
    public function search(Request $request)
    {
        $search      = trim($request->search ?? '');
        $productId   = $request->product_id;
        $warehouseId = $request->warehouse_id;
        $perPage     = (int) ($request->perpage ?? 10);

        if (!$productId) {
            return response()->json([
                'data' => [],
                'has_more' => false
            ]);
        }

        $query = PurchaseReceivedProductItem::query()
            ->leftJoin(
                'als_warehouse',
                'als_warehouse.id',
                '=',
                'als_purchase_receive_product_items.warehouse_id'
            )
            ->leftJoin(
                'als_purchase_receive_product',
                'als_purchase_receive_product.id',
                '=',
                'als_purchase_receive_product_items.receive_product_id'
            )
            ->leftJoin(
                'als_purchase_receive',
                function ($join) {
                    $join->on('als_purchase_receive.id', '=', 'als_purchase_receive_product_items.source_id')
                        ->where('als_purchase_receive_product_items.source_type', '=', 'purchase_receive')
                        ->orOn('als_purchase_receive.id', '=', 'als_purchase_receive_product.receive_id');
                }
            )
            ->leftJoin(
                'als_purchase_invoice',
                function ($join) {
                    $join->on('als_purchase_invoice.id', '=', 'als_purchase_receive_product_items.source_id')
                        ->where('als_purchase_receive_product_items.source_type', '=', 'purchase_invoice');
                }
            )
            ->where('als_purchase_receive_product_items.product_id', $productId)
            ->when($warehouseId, function ($query) use ($warehouseId) {
                $query->where(
                    'als_purchase_receive_product_items.warehouse_id',
                    $warehouseId
                );
            })
            ->where('als_purchase_receive_product_items.qty_left', '>', 0)
            ->where('als_purchase_receive_product_items.status', 'open')
            ->select([
                'als_purchase_receive_product_items.*',
                'als_warehouse.warehouse_name',
                DB::raw("CASE
                    WHEN als_purchase_receive_product_items.source_type = 'purchase_invoice' THEN 'Invoice Pembelian'
                    ELSE 'Penerimaan'
                END as source_label"),
                DB::raw("CASE
                    WHEN als_purchase_receive_product_items.source_type = 'purchase_invoice' THEN als_purchase_invoice.invoice_no
                    ELSE als_purchase_receive.receive_no
                END as source_no"),
                DB::raw("CASE
                    WHEN als_purchase_receive_product_items.source_type = 'purchase_invoice' THEN als_purchase_invoice.invoice_date
                    ELSE als_purchase_receive.receive_date
                END as source_date"),
            ]);

        // 🔍 SEARCH batch / serial
        if ($search !== '') {
            $query->where(
                'als_purchase_receive_product_items.identity_value',
                'like',
                "%{$search}%"
            );
        }

        // 🔥 FEFO → expired duluan tampil di atas
        $query->orderBy('als_warehouse.warehouse_name')
            ->orderByRaw('als_purchase_receive_product_items.expired_date IS NULL')
            ->orderBy('als_purchase_receive_product_items.expired_date', 'asc')
            ->orderBy('als_purchase_receive_product_items.id', 'asc');

        if ($request->boolean('all')) {
            return response()->json([
                'data' => $query->get(),
                'has_more' => false
            ]);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'has_more' => $paginator->hasMorePages()
        ]);
    }
}
