<?php

namespace Icso\Accounting\Http\Controllers\Persediaan;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedProductItem;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

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
