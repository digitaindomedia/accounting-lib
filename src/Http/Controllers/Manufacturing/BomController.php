<?php

namespace Icso\Accounting\Http\Controllers\Manufacturing;

use Icso\Accounting\Http\Requests\CreateBomRequest;
use Icso\Accounting\Repositories\Manufacturing\Bom\BomRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class BomController extends Controller
{
    protected $bomRepo;
    protected $data = [];

    public function __construct(BomRepo $bomRepo)
    {
        $this->bomRepo = $bomRepo;
    }

    public function getAllData(Request $request): JsonResponse
    {
        $search = $request->input('q');
        $page = (int) $request->input('page', 0);
        $perpage = (int) $request->input('perpage', 10);
        $filters = array_filter($request->only(['product_id', 'use_case', 'status']), fn ($value) => $value !== null && $value !== '');

        $data = $this->bomRepo->getAllDataBy($search, $page, $perpage, $filters);
        $total = $this->bomRepo->getAllTotalDataBy($search, $filters);
        $hasMore = Helpers::hasMoreData($total, $page, $data);

        $this->data['status'] = count($data) > 0;
        $this->data['message'] = count($data) > 0 ? 'Data berhasil ditemukan' : 'Data tidak ditemukan';
        $this->data['data'] = count($data) > 0 ? $data : [];
        $this->data['has_more'] = $hasMore;
        $this->data['total'] = $total;

        return response()->json($this->data);
    }

    public function store(CreateBomRequest $request): JsonResponse
    {
        $res = $this->bomRepo->store($request);

        $this->data['status'] = $res;
        $this->data['message'] = $res ? 'Data berhasil disimpan' : 'Data gagal disimpan';
        $this->data['data'] = '';

        return response()->json($this->data, $res ? 200 : 500);
    }

    public function show(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $res = $this->bomRepo->findOne($id, [], ['product', 'outputUnit', 'items', 'items.product', 'items.unit']);

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
            $this->bomRepo->deleteAdditional($id);
            $this->bomRepo->delete($id);
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
                $this->bomRepo->deleteAdditional($id);
                $this->bomRepo->delete($id);
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

    public function preview(Request $request): JsonResponse
    {
        $bomId = (int) $request->input('bom_id');
        $outputQty = (float) $request->input('output_qty', 1);
        $warehouseId = $request->input('warehouse_id');
        $stockDate = $request->input('stock_date');

        if ($bomId <= 0 || $outputQty <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'BOM dan output qty wajib diisi.',
                'data' => '',
            ], 422);
        }

        $data = $this->bomRepo->previewRequirements($bomId, $outputQty, !empty($warehouseId) ? (int) $warehouseId : null, $stockDate);
        if (empty($data)) {
            return response()->json([
                'status' => false,
                'message' => 'Data BOM tidak ditemukan.',
                'data' => '',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Preview kebutuhan bahan berhasil dibuat',
            'data' => $data,
        ]);
    }
}
