<?php

namespace Icso\Accounting\Http\Controllers\Pembelian;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\PurchaseRequestExport;
use Icso\Accounting\Exports\PurchaseRequestReportDetailExport;
use Icso\Accounting\Exports\SamplePurchaseRequestExport;
use Icso\Accounting\Http\Requests\CreatePurchaseRequestRequest;
use Icso\Accounting\Imports\PurchaseRequestImport;
use Icso\Accounting\Models\ImportLog;
use Icso\Accounting\Models\ImportLogDetail;
use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequest;
use Icso\Accounting\Repositories\Pembelian\Request\RequestRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\TransactionsCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class RequestController extends Controller
{
    protected $requestRepo;

    public function __construct(RequestRepo $requestRepo)
    {
        $this->requestRepo = $requestRepo;
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $filters = $this->buildFilters($request);
        $data = $this->requestRepo->getAllDataBetweenBy(
            $search,
            $page,
            $perpage,
            $filters['where'],
            $filters['whereBetween']
        );
        $total = $this->requestRepo->getAllTotalDataBetweenBy(
            $search,
            $filters['where'],
            $filters['whereBetween']
        );

        $hasMore = Helpers::hasMoreData($total,$page,$data);
        if(count($data) > 0) {
            foreach ($data as $item){
                $findAvailableProduct = $this->requestRepo->findInUseInOrder($item->id);
                $item->available_product = $findAvailableProduct['request_product'];
            }
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        }
        else {
            $this->data['status'] = false;
            $this->data['data'] = array();
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);

    }

    public function store(CreatePurchaseRequestRequest $request){
        $res = $this->requestRepo->store($request);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
        }
        return response()->json($this->data);
    }

    public function show(Request $request){
        $res = $this->requestRepo->findOne($request->id,array(),['requestproduct','requestproduct.unit','requestproduct.product', 'requestmeta']);
        if($res){
            $transaksi = $this->requestRepo->getTransaksi($request->id);
            $res->transactions = $transaksi;
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function delete(Request $request){
        $res = $this->requestRepo->destroy($request->id, (int) $request->user_id);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal dihapus";
        }
        return response()->json($this->data);
    }

    public function deleteAll(Request $request)
    {
        $reqData = $request->input('ids', $request->input('params.ids', []));
        $userId = (int) $request->user_id;
        $successDelete = 0;
        $failedDelete = 0;

        if (!is_array($reqData)) {
            $reqData = json_decode(json_encode($reqData), true) ?: [];
        }

        if(count($reqData) > 0){
            foreach ($reqData as $id){
                $requestId = is_array($id) ? ($id['id'] ?? null) : $id;
                if (!$requestId) {
                    $failedDelete = $failedDelete + 1;
                    Log::error('[RequestController][deleteAll] ID permintaan pembelian tidak valid', [
                        'payload_id' => $id,
                        'user_id' => $userId,
                    ]);
                    continue;
                }
                try {
                    $res = $this->requestRepo->destroy((int) $requestId, $userId);
                    if($res){
                        $successDelete = $successDelete + 1;
                    } else {
                        $failedDelete = $failedDelete + 1;
                        Log::error('[RequestController][deleteAll] Permintaan pembelian gagal dihapus', [
                            'request_id' => (int) $requestId,
                            'user_id' => $userId,
                        ]);
                    }
                }
                catch (\Throwable $e) {
                    $failedDelete = $failedDelete + 1;
                    Log::error('[RequestController][deleteAll] Error hapus permintaan pembelian', [
                        'request_id' => (int) $requestId,
                        'user_id' => $userId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        if($successDelete > 0) {
            $this->data['status'] = true;
            $this->data['message'] = "$successDelete Data berhasil dihapus <br /> $failedDelete Data tidak bisa dihapus";
            $this->data['data'] = array();
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function completion()
    {
        $completed = PurchaseRequest::where('request_status', StatusEnum::SELESAI)->count();
        $outstanding = PurchaseRequest::whereIn('request_status', [StatusEnum::OPEN, StatusEnum::PARSIAL_ORDER])->count();
        $all = PurchaseRequest::count();
        $arrData = [
            ['name' => 'Completed (Sudah Diorder)', 'total' => $completed],
            ['name' => 'Outstanding', 'total' => $outstanding],
            ['name' => 'Total', 'total' => $all],
        ];
        $this->data['status'] = true;
        $this->data['message'] = 'Data berhasil ditemukan';
        $this->data['data'] = $arrData;
        return response()->json($this->data);
    }

    public function downloadSample()
    {
        return Excel::download(new SamplePurchaseRequestExport(), 'sample_permintaan_pembelian.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $import = new PurchaseRequestImport($userId);
        Excel::import($import, $request->file('file'));
        $importLogId = $this->createImportLog(
            $userId,
            TransactionsCode::PERMINTAAN_PEMBELIAN,
            $import->getImportedIds()
        );

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows(), 'import_log_id' => $importLogId]);
        }

        return response()->json(['status' => true,'success' => $import->getSuccessCount(),'errors' => count($import->getErrors()), 'message' => 'File berhasil import', 'imported' => $import->getTotalRows(), 'import_log_id' => $importLogId], 200);
    }

    private function createImportLog($userId, string $transactionType, array $transactionIds)
    {
        $transactionIds = array_values(array_unique(array_filter($transactionIds)));
        if (empty($transactionIds)) {
            return null;
        }

        return DB::transaction(function () use ($userId, $transactionType, $transactionIds) {
            $importLog = ImportLog::create([
                'import_at' => date('Y-m-d H:i:s'),
                'user_id' => !empty($userId) ? $userId : null,
                'transaction_type' => $transactionType,
                'total_detail' => count($transactionIds),
            ]);

            $details = array_map(function ($transactionId) use ($importLog) {
                return [
                    'import_log_id' => $importLog->id,
                    'transaksi_id' => $transactionId,
                ];
            }, $transactionIds);

            ImportLogDetail::insert($details);

            return $importLog->id;
        });
    }

    public function getImportLogs(Request $request)
    {
        $page = (int) ($request->page ?? 0);
        $perpage = (int) ($request->perpage ?? 10);
        $perpage = $perpage > 0 ? $perpage : 10;

        $query = ImportLog::where('transaction_type', TransactionsCode::PERMINTAAN_PEMBELIAN)
            ->withCount('details')
            ->orderBy('import_at', 'desc')
            ->orderBy('id', 'desc');

        $total = (clone $query)->count();
        $data = $query->offset($page)->limit($perpage)->get();

        return response()->json([
            'status' => count($data) > 0,
            'message' => count($data) > 0 ? 'Data berhasil ditemukan' : 'Data tidak ditemukan',
            'data' => $data,
            'total' => $total,
            'has_more' => Helpers::hasMoreData($total, $page, $data),
        ]);
    }

    public function showImportLog(Request $request)
    {
        $id = $request->id;
        $data = ImportLog::where('transaction_type', TransactionsCode::PERMINTAAN_PEMBELIAN)
            ->with([
                'details',
                'details.purchaseRequest',
                'details.purchaseRequest.requestproduct',
                'details.purchaseRequest.requestproduct.product',
                'details.purchaseRequest.requestproduct.unit'
            ])
            ->find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $data,
        ]);
    }

    public function deleteImportLogData(Request $request)
    {
        $id = $request->id;
        $userId = (int) ($request->user_id ?? 0);
        $importLog = ImportLog::where('transaction_type', TransactionsCode::PERMINTAAN_PEMBELIAN)
            ->with('details')
            ->find($id);

        if (!$importLog) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'data' => [],
            ]);
        }

        $successDelete = 0;
        $failedDelete = 0;
        $missingData = 0;
        $deletedDetailIds = [];

        foreach ($importLog->details as $detail) {
            $transactionId = (int) $detail->transaksi_id;

            if (!PurchaseRequest::whereKey($transactionId)->exists()) {
                $missingData++;
                $deletedDetailIds[] = $detail->id;
                continue;
            }

            if ($this->requestRepo->destroy($transactionId, $userId)) {
                $successDelete++;
                $deletedDetailIds[] = $detail->id;
            } else {
                $failedDelete++;
            }
        }

        if (!empty($deletedDetailIds)) {
            ImportLogDetail::whereIn('id', $deletedDetailIds)->delete();
        }

        $remaining = ImportLogDetail::where('import_log_id', $importLog->id)->count();
        if ($remaining === 0) {
            $importLog->delete();
        } else {
            $importLog->total_detail = $remaining;
            $importLog->save();
        }

        $message = "$successDelete Data berhasil dihapus <br /> $failedDelete Data tidak bisa dihapus";
        if ($missingData > 0) {
            $message .= " <br /> $missingData Data sudah tidak ada";
        }

        return response()->json([
            'status' => ($successDelete + $missingData) > 0,
            'message' => $message,
            'data' => [],
        ]);
    }

    public function export(Request $request)
    {
       return $this->exportAsFormat($request, 'permintaan-pembelian.xlsx');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $filters = $this->buildFilters($request);
        $total = $this->requestRepo->getAllTotalDataBetweenBy(
            $request->q,
            $filters['where'],
            $filters['whereBetween']
        );
        $data = $this->requestRepo->getAllDataBetweenBy(
            $request->q,
            $request->page,
            $total,
            $filters['where'],
            $filters['whereBetween']
        );
        //logger()->info('Export Data: ', $data->toArray());
        return Excel::download(new PurchaseRequestExport($data), $filename);
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'permintaan-pembelian.csv');
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->buildFilters($request);
        $total = $this->requestRepo->getAllTotalDataBetweenBy(
            $request->q,
            $filters['where'],
            $filters['whereBetween']
        );
        $data = $this->requestRepo->getAllDataBetweenBy(
            $request->q,
            $request->page,
            $total,
            $filters['where'],
            $filters['whereBetween']
        );
        $export = new PurchaseRequestExport($data);
        $pdf = PDF::loadView('accounting::purchase.purchase_request_pdf', ['arrData' => $export->getData()]);

        return $pdf->download('permintaan-pembelian.pdf');
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $status = $request->status;
        $sifat = $request->sifat;
        return compact('search', 'page', 'perpage', 'status','sifat','fromDate','untilDate');
    }

    private function buildFilters(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $where = [];
        $whereBetween = [];

        if (!empty($fromDate) && !empty($untilDate)) {
            $whereBetween = [$fromDate, $untilDate];
        }

        if (!empty($status)) {
            if ($status == 'available') {
                $where[] = ['method' => 'where', 'value' => [['request_status', '=', StatusEnum::OPEN]]];
                $where[] = ['method' => 'orWhere', 'value' => [['request_status', '=', StatusEnum::PARSIAL_ORDER]]];
            } else {
                $where[] = ['method' => 'where', 'value' => [['request_status', '=', $status]]];
            }
        }

        if (!empty($sifat)) {
            $where[] = ['method' => 'where', 'value' => [['urgency', '=', $sifat]]];
        }

        return compact('where', 'whereBetween');
    }

    public function exportReportExcel(Request $request)
    {
        $params = $this->setQueryParameters($request);
        $filters = $this->buildFilters($request);
        extract($params);
        $data = $this->requestRepo->getAllDataBetweenBy(
            $search,
            $page,
            $perpage,
            $filters['where'],
            $filters['whereBetween']
        );
        return Excel::download(new PurchaseRequestReportDetailExport($data,$params), 'excel-purchase-request.xlsx');
    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        $filters = $this->buildFilters($request);
        extract($params);
        $data = $this->requestRepo->getAllDataBetweenBy(
            $search,
            $page,
            $perpage,
            $filters['where'],
            $filters['whereBetween']
        );

        // Render the same Blade report view to PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::purchase.purchase_request_detail_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-permintaan-pembelian.pdf');
        }

        return $pdf->download('laporan-permintaan-pembelian.pdf');
    }

}
