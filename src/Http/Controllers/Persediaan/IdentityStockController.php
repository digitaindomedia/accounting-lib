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

        if (!$productId || !$warehouseId) {
            return response()->json([
                'data' => [],
                'has_more' => false
            ]);
        }

        $query = PurchaseReceivedProductItem::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_left', '>', 0)
            ->where('status', 'open');

        // 🔍 SEARCH batch / serial
        if ($search !== '') {
            $query->where('identity_value', 'like', "%{$search}%");
        }

        // 🔥 FEFO → expired duluan tampil di atas
        $query->orderByRaw('expired_date IS NULL')
            ->orderBy('expired_date', 'asc')
            ->orderBy('id', 'asc');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'has_more' => $paginator->hasMorePages()
        ]);
    }
}