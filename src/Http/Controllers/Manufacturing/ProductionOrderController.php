<?php

namespace Icso\Accounting\Http\Controllers\Manufacturing;

use Icso\Accounting\Http\Requests\CreateProductionOrderRequest;
use Icso\Accounting\Repositories\Manufacturing\Production\ProductionOrderRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ProductionOrderController extends Controller
{
    protected $productionOrderRepo;
    protected $data = [];

    public function __construct(ProductionOrderRepo $productionOrderRepo)
    {
        $this->productionOrderRepo = $productionOrderRepo;
    }

    private function setQueryParameters(Request $request): array
    {
        $search = $request->input('q');
        $page = (int) $request->input('page', 0);
        $perpage = (int) $request->input('perpage', 10);
        $fromDate = $request->input('from_date');
        $untilDate = $request->input('until_date');
        $warehouseId = $request->input('warehouse_id');
        $statusProduction = $request->input('status_production');
        $productId = $request->input('product_id');

        $where = [];
        if (!empty($warehouseId)) {
            $where[] = ['method' => 'where', 'value' => [['warehouse_id', '=', $warehouseId]]];
        }
        if (!empty($statusProduction)) {
            $where[] = ['method' => 'where', 'value' => [['status_production', '=', $statusProduction]]];
        }
        if (!empty($productId)) {
            $where[] = ['method' => 'where', 'value' => [['product_id', '=', $productId]]];
        }
        if (!empty($fromDate) && !empty($untilDate)) {
            $where[] = ['method' => 'whereBetween', 'value' => ['field' => 'production_date', 'value' => [$fromDate, $untilDate]]];
        }

        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request): JsonResponse
    {
        $params = $this->setQueryParameters($request);
        $data = $this->productionOrderRepo->getAllDataBy($params['search'], $params['page'], $params['perpage'], $params['where']);
        $total = $this->productionOrderRepo->getAllTotalDataBy($params['search'], $params['where']);
        $hasMore = Helpers::hasMoreData($total, $params['page'], $data);

        $this->data['status'] = count($data) > 0;
        $this->data['message'] = count($data) > 0 ? 'Data berhasil ditemukan' : 'Data tidak ditemukan';
        $this->data['data'] = count($data) > 0 ? $data : [];
        $this->data['has_more'] = $hasMore;
        $this->data['total'] = $total;

        return response()->json($this->data);
    }

    public function store(CreateProductionOrderRequest $request): JsonResponse
    {
        $res = $this->productionOrderRepo->store($request);

        $this->data['status'] = $res;
        $this->data['message'] = $res ? 'Data berhasil disimpan' : 'Data gagal disimpan';
        $this->data['data'] = '';

        return response()->json($this->data, $res ? 200 : 500);
    }

    public function show(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $res = $this->productionOrderRepo->findOne($id, [], [
            'warehouse',
            'bom',
            'product',
            'outputUnit',
            'materials',
            'materials.product',
            'materials.unit',
            'results',
            'results.product',
            'results.unit',
        ]);

        $this->data['status'] = !empty($res);
        $this->data['message'] = !empty($res) ? 'Data berhasil ditemukan' : 'Data tidak ditemukan';
        $this->data['data'] = !empty($res) ? $res : '';

        return response()->json($this->data, !empty($res) ? 200 : 404);
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = $request->input('id');
        DB::beginTransaction();
        try {
            $this->productionOrderRepo->deleteAdditional($id);
            $this->productionOrderRepo->delete($id);
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Data berhasil dihapus', 'data' => []]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Data gagal dihapus', 'data' => []], 500);
        }
    }

    public function deleteAll(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array']);
        $successDelete = 0;
        $failedDelete = 0;

        foreach ($request->input('ids') as $id) {
            DB::beginTransaction();
            try {
                $this->productionOrderRepo->deleteAdditional($id);
                $this->productionOrderRepo->delete($id);
                DB::commit();
                $successDelete++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $failedDelete++;
            }
        }

        return response()->json([
            'status' => $successDelete > 0,
            'message' => $successDelete > 0
                ? "$successDelete Data berhasil dihapus <br /> $failedDelete Data tidak bisa dihapus"
                : 'Data gagal dihapus',
            'data' => [],
        ]);
    }
}
