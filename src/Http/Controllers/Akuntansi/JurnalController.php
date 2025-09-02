<?php

namespace Icso\Accounting\Http\Controllers\Akuntansi;

use Icso\Accounting\Exports\JurnalKasBankExport;
use Icso\Accounting\Exports\JurnalUmumExport;
use Icso\Accounting\Exports\SampleJurnalKasBankExport;
use Icso\Accounting\Exports\SampleJurnalUmumExport;
use Icso\Accounting\Http\Requests\CreateJurnalRequest;
use Icso\Accounting\Imports\JurnalKasBankImport;
use Icso\Accounting\Imports\JurnalUmumImport;
use Icso\Accounting\Repositories\Akuntansi\Jurnal\JurnalAkunRepo;
use Icso\Accounting\Repositories\Akuntansi\Jurnal\JurnalRepo;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Icso\Accounting\Utils\JurnalType;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class JurnalController extends Controller
{
    protected $jurnalRepo;
    protected $jurnalAkunRepo;
    protected $transaksiRepo;
    protected $coaRepo;

    public function __construct(JurnalRepo $jurnalRepo, JurnalAkunRepo $jurnalAkunRepo, JurnalTransaksiRepo $transaksiRepo, CoaRepo $coaRepo)
    {
        $this->jurnalRepo = $jurnalRepo;
        $this->jurnalAkunRepo = $jurnalAkunRepo;
        $this->transaksiRepo = $transaksiRepo;
        $this->coaRepo = $coaRepo;
    }

    public function getAllData(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
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
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function store(CreateJurnalRequest $request){
        $res = $this->jurnalRepo->store($request);
        if($res)
        {
            $this->data['status'] = true;
            $this->data['data'] = '';
            $this->data['message'] = 'Data berhasil disimpan';

        }else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function findJurnalById(Request $request) {
        $id = $request->id;
        $res = $this->jurnalRepo->findOne($id, array(),['jurnal_akun', 'coa','jurnal_akun.coa', 'jurnal_meta']);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        }
        else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function deleteById(Request $request) {
        $id = $request->id;
        $res = $this->jurnalRepo->delete($id);
        if($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus ';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function deleteAllJurnal(Request $request) {
        $reqData = json_decode(json_encode($request->ids));
        $successDelete = 0;
        $failedDelete = 0;
        if(count($reqData) > 0){
            foreach ($reqData as $item){
                if($item->can_delete){
                    $res = $this->jurnalRepo->delete($item->id);
                    if($res){
                        $successDelete = $successDelete + 1;
                    } else{
                        $failedDelete = $failedDelete + 1;
                    }
                }

            }
        }

        if($successDelete > 0) {
            $this->data['status'] = true;
            $this->data['message'] = "$successDelete Data berhasil dihapus";
            $this->data['data'] = array();
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function showAccountJurnal(Request $request){
        $noJurnal = $request->jurnal_no;
        $findJurnal =$this->transaksiRepo->findAllByWhere(array('transaction_no' => $noJurnal),array(),['coa']);
        if(count($findJurnal) > 0){
            $this->data['status'] = true;
            $this->data['message'] = "Data ditemukan";
            $this->data['data'] = $findJurnal;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = " Data tidak ditemukan";
            $this->data['data'] = array();
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
        $userId = $request->user_id;
        $import = new JurnalUmumImport($userId);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['errors' => $errors], 422);
        }

        return response()->json(['message' => 'File imported successfully'], 200);
    }

    public function importKasBank(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $transType = $request->trans_type;
        $import = new JurnalKasBankImport($userId, $transType);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['errors' => $errors], 422);
        }

        return response()->json(['message' => 'File imported successfully'], 200);
    }

    public function export(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $where = $this->buildWhere($request);
        $total = $this->jurnalRepo->getAllTotalDataBy($search, $where);
        $data = $this->jurnalRepo->getAllDataBy($search, $page, $total, $where);
        return $this->handleExport($data, $request->jurnal_type);

    }

    public function exportPdf(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $where = $this->buildWhere($request);
        $total = $this->jurnalRepo->getAllTotalDataBy($search, $where);
        $data = $this->jurnalRepo->getAllDataBy($search, $page, $total, $where);
        return $this->handleExportPdf($data, $request->jurnal_type);
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
            $pdf = PDF::loadView('jurnal/jurnal_kas_bank_pdf', ['arrData' => (new JurnalKasBankExport($data))->collection()]);
            return $pdf->download('jurnal-kas-bank.pdf');
        }
        $pdf = PDF::loadView('jurnal/jurnal_umum_pdf', ['arrData' => (new JurnalUmumExport($data))->collection()]);
        return $pdf->download('jurnal-umum.pdf');
    }

    private function buildWhere(Request $request)
    {
        $where = ['jurnal_type' => $request->jurnal_type ?: JurnalType::JURNAL_UMUM];
        if ($request->coa_id) {
            $where['coa_id'] = $request->coa_id;
        }
        if ($request->from_date && $request->until_date) {
            $where['jurnal_date'] = [$request->from_date, $request->until_date];
        }
        return $where;
    }
}
