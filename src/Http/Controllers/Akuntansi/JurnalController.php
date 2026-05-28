<?php

namespace Icso\Accounting\Http\Controllers\Akuntansi;

use Icso\Accounting\Exports\JurnalKasBankExport;
use Icso\Accounting\Exports\JurnalUmumExport;
use Icso\Accounting\Exports\SampleJurnalKasBankExport;
use Icso\Accounting\Exports\SampleJurnalUmumExport;
use Icso\Accounting\Http\Requests\CreateJurnalRequest;
use Icso\Accounting\Imports\JurnalKasBankImport;
use Icso\Accounting\Imports\JurnalUmumImport;
use Icso\Accounting\Models\Akuntansi\Jurnal;
use Icso\Accounting\Models\ImportLog;
use Icso\Accounting\Models\ImportLogDetail;
use Icso\Accounting\Repositories\Akuntansi\Jurnal\JurnalAkunRepo;
use Icso\Accounting\Repositories\Akuntansi\Jurnal\JurnalRepo;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\JurnalType;
use Icso\Accounting\Utils\TransactionsCode;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class JurnalController extends Controller
{
    protected $jurnalRepo;
    protected $jurnalAkunRepo;
    protected $transaksiRepo;
    protected $coaRepo;
    protected $data = [];

    public function __construct(JurnalRepo $jurnalRepo, JurnalAkunRepo $jurnalAkunRepo, JurnalTransaksiRepo $transaksiRepo, CoaRepo $coaRepo)
    {
        $this->jurnalRepo = $jurnalRepo;
        $this->jurnalAkunRepo = $jurnalAkunRepo;
        $this->transaksiRepo = $transaksiRepo;
        $this->coaRepo = $coaRepo;
    }

    public function getAllData(Request $request): JsonResponse
    {
        $search = $request->input('q');
        $page = $request->input('page');
        $perpage = $request->input('perpage');
        $where = $this->buildWhere($request);
        
        $data = $this->jurnalRepo->getAllDataBy($search, $page, $perpage, $where);
        $total = $this->jurnalRepo->getAllTotalDataBy($search, $where);
        
        if (count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['total'] = $total;

        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function store(CreateJurnalRequest $request): JsonResponse
    {
        $res = $this->jurnalRepo->store($request);
        if ($res) {
            $this->data['status'] = true;
            $this->data['data'] = '';
            $this->data['message'] = 'Data berhasil disimpan';
            return response()->json($this->data, 200);
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
            $this->data['data'] = [];
            return response()->json($this->data, 200);
        }
    }

    public function findJurnalById(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['status' => false, 'message' => 'ID tidak ditemukan', 'data' => ''], 400);
        }

        $res = $this->jurnalRepo->findOne($id, [], ['jurnal_akun', 'coa', 'jurnal_akun.coa', 'jurnal_meta']);
        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function deleteById(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['status' => false, 'message' => 'ID tidak ditemukan', 'data' => ''], 400);
        }

        $res = $this->jurnalRepo->destroy($id, (int) $request->input('user_id'));
        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus ';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function deleteAllJurnal(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array'
        ]);

        $reqData = $request->input('ids');
        $successDelete = 0;
        $failedDelete = 0;
        
        if (count($reqData) > 0) {
            foreach ($reqData as $item) {
                // Handle both object (from json_decode) and array (from input) structures if needed,
                // but since we use input('ids') and validate array, we expect array of objects or IDs.
                // Assuming input is array of objects with 'id' and 'can_delete' properties based on original code logic
                // Or if it's just IDs, we need to adjust. Original code: $item->can_delete
                
                // Safe access to properties
                $itemId = is_array($item) ? ($item['id'] ?? null) : ($item->id ?? null);
                $canDelete = is_array($item) ? ($item['can_delete'] ?? false) : ($item->can_delete ?? false);

                if ($itemId && $canDelete) {
                    $res = $this->jurnalRepo->destroy($itemId, (int) $request->input('user_id'));
                    if ($res) {
                        $successDelete++;
                    } else {
                        $failedDelete++;
                    }
                }
            }
        }

        if ($successDelete > 0) {
            $this->data['status'] = true;
            $this->data['message'] = "$successDelete Data berhasil dihapus";
            $this->data['data'] = [];
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function showAccountJurnal(Request $request): JsonResponse
    {
        $noJurnal = $request->input('jurnal_no');
        if (!$noJurnal) {
             return response()->json(['status' => false, 'message' => 'Nomor Jurnal tidak ditemukan', 'data' => []], 400);
        }

        $findJurnal = $this->transaksiRepo->findAllByWhere(['transaction_no' => $noJurnal], [], ['coa']);
        if (count($findJurnal) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = "Data ditemukan";
            $this->data['data'] = $findJurnal;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = " Data tidak ditemukan";
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function downloadSample()
    {
        return Excel::download(new SampleJurnalUmumExport(), 'sample_jurnal_umum.xlsx');
    }

    public function downloadKasBankSample()
    {
        return Excel::download(new SampleJurnalKasBankExport(), 'sample_jurnal_kas_bank_giro.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->input('user_id');
        $import = new JurnalUmumImport($userId);
        Excel::import($import, $request->file('file'));
        $importLogId = $this->createImportLog(
            $userId,
            TransactionsCode::JURNAL,
            $import->getImportedIds()
        );

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows(), 'import_log_id' => $importLogId]);
        }

        return response()->json(['status' => true,'success' => $import->getSuccessCount(),'errors' => count($import->getErrors()), 'message' => 'File berhasil import', 'imported' => $import->getTotalRows(), 'import_log_id' => $importLogId], 200);
    }

    public function importKasBank(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->input('user_id');
        $transType = $request->input('trans_type');
        $import = new JurnalKasBankImport($userId, $transType);
        Excel::import($import, $request->file('file'));
        $importLogId = $this->createImportLog(
            $userId,
            TransactionsCode::JURNAL,
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
        $page = (int) ($request->input('page') ?? 0);
        $perpage = (int) ($request->input('perpage') ?? 10);
        $perpage = $perpage > 0 ? $perpage : 10;

        $query = ImportLog::where('transaction_type', TransactionsCode::JURNAL)
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
        $id = $request->input('id');
        $data = ImportLog::where('transaction_type', TransactionsCode::JURNAL)
            ->with([
                'details',
                'details.jurnal',
                'details.jurnal.coa',
                'details.jurnal.jurnal_akun',
                'details.jurnal.jurnal_akun.coa'
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
        $id = $request->input('id');
        $userId = (int) ($request->input('user_id') ?? 0);
        $importLog = ImportLog::where('transaction_type', TransactionsCode::JURNAL)
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
            $jurnal = Jurnal::with('jurnal_akun')->find($transactionId);

            if (!$jurnal) {
                $missingData++;
                $deletedDetailIds[] = $detail->id;
                continue;
            }

            if (!$jurnal->can_delete) {
                $failedDelete++;
                continue;
            }

            if ($this->jurnalRepo->destroy($transactionId, $userId)) {
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
        $search = $request->input('q');
        $page = $request->input('page');
        $where = $this->buildWhere($request);
        $total = $this->jurnalRepo->getAllTotalDataBy($search, $where);
        $data = $this->jurnalRepo->getAllDataBy($search, $page, $total, $where);
        return $this->handleExport($data, $request->input('jurnal_type'));

    }

    public function exportPdf(Request $request)
    {
        $search = $request->input('q');
        $page = $request->input('page');
        $where = $this->buildWhere($request);
        $total = $this->jurnalRepo->getAllTotalDataBy($search, $where);
        $data = $this->jurnalRepo->getAllDataBy($search, $page, $total, $where);
        return $this->handleExportPdf($data, $request->input('jurnal_type'));
    }

    private function handleExport($data, $jurnalType)
    {
        if ($jurnalType) {
            return Excel::download(new JurnalKasBankExport($data), 'jurnal-kas-bank.xlsx');
        }
        return Excel::download(new JurnalUmumExport($data), 'jurnal-umum.xlsx');
    }

    private function handleExportPdf($data, $jurnalType)
    {
        if ($jurnalType) {
            $pdf = Pdf::loadView('accounting::jurnal.jurnal_kas_bank_pdf', ['arrData' => (new JurnalKasBankExport($data))->collection()]);
            return $pdf->download('jurnal-kas-bank.pdf');
        }
        $pdf = Pdf::loadView('accounting::jurnal.jurnal_umum_pdf', ['arrData' => (new JurnalUmumExport($data))->collection()]);
        return $pdf->download('jurnal-umum.pdf');
    }

    private function buildWhere(Request $request)
    {
        $jurnalType = $request->input('jurnal_type');
        $where = ['jurnal_type' => $jurnalType ?: JurnalType::JURNAL_UMUM];
        
        if ($request->input('coa_id')) {
            $where['coa_id'] = $request->input('coa_id');
        }
        if ($request->input('from_date') && $request->input('until_date')) {
            $where['jurnal_date'] = [$request->input('from_date'), $request->input('until_date')];
        }
        return $where;
    }
}
