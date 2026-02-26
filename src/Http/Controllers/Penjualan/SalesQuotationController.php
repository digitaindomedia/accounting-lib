<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Repositories\Penjualan\Quotation\SalesQuotationRepository;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesQuotationController extends Controller
{
    protected SalesQuotationRepository $salesQuotationService;

    public function __construct(SalesQuotationRepository $salesQuotationService)
    {
        $this->salesQuotationService = $salesQuotationService;
    }

    public function getAllData(Request $request)
    {
        $search = $request->input('q');
        $page = (int) $request->input('page', 0);
        $perpage = (int) $request->input('perpage', 10);
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $where = [];

        // Dipakai untuk async selector di Sales Order agar quotation full-used (close) tidak muncul.
        if ($request->boolean('for_sales_order')) {
            $where[] = ['quotation_status', '!=', StatusEnum::CLOSE];
        }

        $res = $this->salesQuotationService->getAll($search, $page, $perpage, $where, $fromDate, $untilDate);
        $data = $res['data'] ?? [];
        $total = $res['total'] ?? 0;

        $has_more = false;
        $pageOffset = $page + count($data);
        if ($total > $pageOffset) {
            $has_more = true;
        }

        if ($data) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['total'] = $total;
            $this->data['has_more'] = $has_more;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal ditemukan';
        }

        return response()->json($this->data);
    }

    public function show(Request $request)
    {
        $id = $request->id;
        $res = $this->salesQuotationService->find($id);

        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal ditemukan';
        }

        return response()->json($this->data);
    }

    public function store(Request $request)
    {
        $rules = [
            'quotation_date' => 'required|date',
            'quotationproduct.*.product_id' => 'required|string',
            'note' => 'nullable|string'
        ];

        $validator = Validator::make($request->all(), $rules, [
            'quotation_date.required' => 'Tanggal quotation tidak boleh kosong',
            'quotationproduct.required' => 'Daftar barang Masih Kosong',
        ]);

        if ($validator->fails()) {
            $this->data['status'] = false;
            $this->data['message'] = $validator->messages()->first();

            return response()->json($this->data);
        }

        $data = $request->all();
        $data['files'] = $request->file('files', []);

        // Generate quotation number if not provide
        $res = $this->salesQuotationService->store($data);

        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
        }

        return response()->json($this->data);
    }

    public function deleteData(Request $request)
    {
        $id = $request->id;
        $res = $this->salesQuotationService->delete($id);

        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
        }

        return response()->json($this->data);
    }

    public function deleteAll(Request $request)
    {
        $ids = $request->ids;
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        $res = $this->salesQuotationService->deleteAll($ids);

        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
        }

        return response()->json($this->data);
    }
}
