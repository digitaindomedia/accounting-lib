<?php

namespace Icso\Accounting\Http\Controllers\Akuntansi;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\BukuBesarExport;
use Icso\Accounting\Exports\JurnalTransaksiExport;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class BukuBesarController extends Controller
{
    protected $jurnalTransaksiRepo;

    public function __construct(JurnalTransaksiRepo $jurnalTransaksiRepo)
    {
        $this->jurnalTransaksiRepo = $jurnalTransaksiRepo;
    }

    public function show(Request $request){
        $data = $this->getFilteredData($request);
        $total = $this->getFilteredTotal($request);

        $arrData = $this->processData($data);

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $arrData,
            'total' => $total,
        ]);
    }

    public function showAll(Request $request){
        $data = $this->getFilteredData($request);
        $total = $this->getFilteredTotal($request);

        $status = count($data) > 0;
        $message = $status ? 'Data berhasil ditemukan' : 'Data tidak ditemukan';

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'total' => $total,
        ]);
    }

    public function exportExcelTransaksi(Request $request)
    {
        $total = $this->getFilteredTotal($request);
        $request->page  = 0;
        $request->perpage = $total;
        $data = $this->getFilteredData($request);
        return Excel::download(new JurnalTransaksiExport($data), 'jurnal-transaksi.xlsx');

    }

    public function exportPdfTransaksi(Request $request)
    {
        $total = $this->getFilteredTotal($request);
        $request->page  = 0;
        $request->perpage = $total;
        $data = $this->getFilteredData($request);
        $export = new JurnalTransaksiExport($data);
        $pdf = PDF::loadView('jurnal_transaksi_pdf', ['jurnalData' => $export->getData()]);

        return $pdf->download('jurnal-transaksi.pdf');

    }

    public function getTotalAkun(Request $request)
    {
        $coaId = $request->input('coa_id');
        $total = $this->calculateTotalAkun($coaId);

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $total,
        ]);

    }

    public function export(Request $request)
    {
        $arrData = $this->getExportData($request);
        return Excel::download(new BukuBesarExport($arrData), 'bukubesar.xlsx');
    }

    public function exportToPdf(Request $request)
    {
        $arrData = $this->getExportData($request);
        $pdf = PDF::loadView('buku_besar_pdf', ['jurnalData' => $arrData]);

        return $pdf->download('bukubesar.pdf');
    }

    private function getFilteredData(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $coaId = $request->coa_id;

        $where = [];
        if (!empty($coaId)) {
            $where[] = ['coa_id', '=', $coaId];
        }

        return $this->jurnalTransaksiRepo->getAllDataWithDateBy($search, $page, $perpage, $where, ['coa_id', 'asc', 'transaction_datetime', 'asc'], ['coa'], ['column' => 'transaction_date', 'range' => [$fromDate, $untilDate]]);
    }

    private function getFilteredTotal(Request $request)
    {
        $search = $request->q;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $coaId = $request->coa_id;

        $where = [];
        if (!empty($coaId)) {
            $where[] = ['coa_id', '=', $coaId];
        }

        return $this->jurnalTransaksiRepo->getAllTotalDataWithDateBy($search, $where, ['coa_id', 'asc', 'transaction_datetime', 'asc'], ['coa'], ['column' => 'transaction_date', 'range' => [$fromDate, $untilDate]]);
    }

    private function processData($data)
    {
        $arrData = [];
        if (count($data) > 0) {
            $unique = $data->unique('coa_id');
            foreach ($unique as $item) {
                $res = JurnalTransaksiRepo::filterArrayByCoaId($data, $item->coa_id);
                $tanggal = $res[0]->transaction_datetime;
                $saldoAwal = JurnalTransaksiRepo::sumSaldoAwal($item->coa_id, $tanggal);
                $arrData[] = [
                    'saldo_awal' => $saldoAwal,
                    'coa' => $item->coa,
                    'data' => $res
                ];
            }
        }
        return $arrData;
    }

    private function calculateTotalAkun($coaId)
    {
        $coaRepo = new CoaRepo(new Coa());
        $query = JurnalTransaksi::query()->where('coa_id', $coaId);
        $totalDebet = $query->sum('debet');
        $totalKredit = $query->sum('kredit');
        $getCoaParent = $coaRepo->getParentRoot($coaId);
        $total = $totalDebet - $totalKredit;
        if ($getCoaParent->coa_position == VarType::COA_POSITION_KREDIT) {
            $total = $totalKredit - $totalDebet;
        }
        return $total;
    }

    private function getExportData(Request $request)
    {
        $data = $this->getFilteredData($request);
        return $this->processData($data);
    }

}
