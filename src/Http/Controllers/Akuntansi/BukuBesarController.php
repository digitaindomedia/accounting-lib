<?php

namespace Icso\Accounting\Http\Controllers\Akuntansi;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Enums\TransactionType;
use Icso\Accounting\Exports\BukuBesarExport;
use Icso\Accounting\Exports\DetailKasBankExport;
use Icso\Accounting\Exports\JurnalTransaksiExport;
use Icso\Accounting\Models\Akuntansi\Jurnal;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\VarType;
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

        $arrData = $this->processData($data, $request, $total);

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
        $pdf = PDF::loadView('accounting::jurnal_transaksi_pdf', ['jurnalData' => $export->getData()]);

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
        $pdf = PDF::loadView('accounting::buku_besar_pdf', ['jurnalData' => $arrData]);

        return $pdf->download('bukubesar.pdf');
    }

    public function getKasAccounts()
    {
        return response()->json([
            'status' => true,
            'message' => 'Data akun kas berhasil ditemukan',
            'data' => $this->getCashAccounts('kas'),
        ]);
    }

    public function showKas(Request $request)
    {
        return $this->showKasBank($request, 'kas', 'kas');
    }

    public function showBank(Request $request)
    {
        return $this->showKasBank($request, 'bank', 'bank');
    }

    private function showKasBank(Request $request, string $category, string $label)
    {
        $coaId = $request->input('coa_id');
        if (empty($coaId)) {
            return response()->json([
                'status' => false,
                'message' => 'Akun ' . $label . ' wajib dipilih',
                'data' => [],
                'total' => 0,
            ], 422);
        }

        if (!$this->isCashAccount($coaId, $category)) {
            return response()->json([
                'status' => false,
                'message' => 'Akun yang dipilih bukan akun ' . $label,
                'data' => [],
                'total' => 0,
            ], 422);
        }

        $data = $this->getFilteredData($request);
        $total = $this->getFilteredTotal($request);
        $arrData = $this->processData($data, $request, $total);

        return response()->json([
            'status' => true,
            'message' => 'Data laporan ' . $label . ' berhasil ditemukan',
            'data' => $arrData,
            'total' => $total,
            'cash_accounts' => $this->getCashAccounts($category),
        ]);
    }

    public function exportKas(Request $request)
    {
        $arrData = $this->getKasExportData($request, 'kas');
        return Excel::download(new BukuBesarExport($arrData), 'kasbank' . date('YmdHis') . '.xlsx');
    }

    public function exportKasToPdf(Request $request)
    {
        $arrData = $this->getKasExportData($request, 'kas');
        $pdf = PDF::loadView('accounting::buku_besar_pdf', ['jurnalData' => $arrData]);

        return $pdf->download('kas-bank' . date('YmdHis') . '.pdf');
    }

    public function exportBank(Request $request)
    {
        $arrData = $this->getKasExportData($request, 'bank');
        return Excel::download(new BukuBesarExport($arrData), 'bank' . date('YmdHis') . '.xlsx');
    }

    public function exportBankToPdf(Request $request)
    {
        $arrData = $this->getKasExportData($request, 'bank');
        $pdf = PDF::loadView('accounting::buku_besar_pdf', ['jurnalData' => $arrData]);

        return $pdf->download('bank' . date('YmdHis') . '.pdf');
    }

    public function showDetailKas(Request $request)
    {
        return $this->showDetailKasBank($request, 'kas', 'Kas');
    }

    public function showDetailBank(Request $request)
    {
        return $this->showDetailKasBank($request, 'bank', 'Bank');
    }

    public function exportDetailKas(Request $request)
    {
        $reportData = $this->getDetailKasBankData($request, 'kas');
        return Excel::download(new DetailKasBankExport($reportData), 'detail-kas' . date('YmdHis') . '.xlsx');
    }

    public function exportDetailKasToPdf(Request $request)
    {
        $reportData = $this->getDetailKasBankData($request, 'kas');
        $pdf = PDF::loadView('accounting::detail_kas_bank_pdf', [
            'title' => $this->getDetailReportTitle($request, 'Kas'),
            'period' => $this->getReportPeriod($request),
            'reportData' => $reportData,
        ]);

        return $pdf->download('detail-kas' . date('YmdHis') . '.pdf');
    }

    public function exportDetailBank(Request $request)
    {
        $reportData = $this->getDetailKasBankData($request, 'bank');
        return Excel::download(new DetailKasBankExport($reportData), 'detail-bank' . date('YmdHis') . '.xlsx');
    }

    public function exportDetailBankToPdf(Request $request)
    {
        $reportData = $this->getDetailKasBankData($request, 'bank');
        $pdf = PDF::loadView('accounting::detail_kas_bank_pdf', [
            'title' => $this->getDetailReportTitle($request, 'Bank'),
            'period' => $this->getReportPeriod($request),
            'reportData' => $reportData,
        ]);

        return $pdf->download('detail-bank' . date('YmdHis') . '.pdf');
    }

    private function showDetailKasBank(Request $request, string $category, string $label)
    {
        $coaId = $request->input('coa_id');
        if (empty($coaId)) {
            return response()->json([
                'status' => false,
                'message' => 'Akun ' . strtolower($label) . ' wajib dipilih',
                'data' => [],
                'total' => 0,
            ], 422);
        }

        if (!$this->isCashAccount($coaId, $category)) {
            return response()->json([
                'status' => false,
                'message' => 'Akun yang dipilih bukan akun ' . strtolower($label),
                'data' => [],
                'total' => 0,
            ], 422);
        }

        $reportData = $this->getDetailKasBankData($request, $category);

        return response()->json([
            'status' => true,
            'message' => 'Data laporan detail ' . strtolower($label) . ' berhasil ditemukan',
            'data' => $reportData,
            'total' => count($reportData),
        ]);
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

        return $this->jurnalTransaksiRepo->getAllDataWithDateBy($search, $page, $perpage, $where, ['coa_id', 'asc', 'transaction_datetime', 'asc', 'id', 'asc'], ['coa'], ['column' => 'transaction_date', 'range' => [$fromDate, $untilDate]]);
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

    private function processData($data, Request $request = null, $filteredTotal = null)
    {
        $arrData = [];
        if (count($data) > 0) {
            $unique = $data->unique('coa_id');
            foreach ($unique as $item) {
                $res = $data->where('coa_id', $item->coa_id)->values();

                if ($res->isEmpty()) continue;

                $firstItem = $res[0];
                $saldoAwal = $this->getSaldoAwal($item->coa_id, $firstItem->transaction_datetime, $firstItem->id);

                $currentSaldo = $saldoAwal;
                $coaPosition = $item->coa->coa_position ?? 'debet';
                $totalDebet = 0;
                $totalKredit = 0;

                foreach ($res as $transaction) {
                    $totalDebet += (float) $transaction->debet;
                    $totalKredit += (float) $transaction->kredit;
                    $amount = 0;
                    if ($coaPosition == 'debet') {
                        $amount = $transaction->debet - $transaction->kredit;
                    } else {
                        $amount = $transaction->kredit - $transaction->debet;
                    }
                    $currentSaldo += $amount;
                    $transaction->saldo = $currentSaldo;
                    if($transaction->transaction_code == TransactionsCode::JURNAL){
                        if(empty($transaction->note)){
                            $findJurnal = Jurnal::where('id', $transaction->transaction_id)->first();
                            if(!empty($findJurnal)){
                                $transaction->note = $findJurnal->note;
                            }
                        }
                    }
                }

                $arrData[] = [
                    'saldo_awal' => $saldoAwal,
                    'total_debet' => $totalDebet,
                    'total_kredit' => $totalKredit,
                    'saldo_akhir' => $currentSaldo,
                    'coa' => $item->coa,
                    'data' => $res
                ];
            }
        } elseif (($filteredTotal === null || $filteredTotal == 0) && !empty($request?->coa_id) && !empty($request?->from_date)) {
            $coa = Coa::find($request->coa_id);
            if (!empty($coa)) {
                $arrData[] = [
                    'saldo_awal' => $this->getSaldoAwalByDate($request->coa_id, $request->from_date),
                    'total_debet' => 0,
                    'total_kredit' => 0,
                    'saldo_akhir' => $this->getSaldoAwalByDate($request->coa_id, $request->from_date),
                    'coa' => $coa,
                    'data' => collect()
                ];
            }
        }
        return $arrData;
    }

    private function getSaldoAwal($coaId, $date, $transactionId)
    {
        $query = JurnalTransaksi::where('coa_id', $coaId)
            ->where(function($q) use ($date, $transactionId) {
                $q->where('transaction_datetime', '<', $date)
                    ->orWhere(function($sub) use ($date, $transactionId) {
                        $sub->where('transaction_datetime', $date)
                            ->where('id', '<', $transactionId);
                    });
            });

        $totalDebet = (clone $query)->sum('debet');
        $totalKredit = (clone $query)->sum('kredit');

        $coa = Coa::find($coaId);
        if ($coa && $coa->coa_position == 'debet') {
            return $totalDebet - $totalKredit;
        }
        return $totalKredit - $totalDebet;
    }

    private function getSaldoAwalByDate($coaId, $date)
    {
        $query = JurnalTransaksi::where('coa_id', $coaId)
            ->where('transaction_date', '<', $date);

        $totalDebet = (clone $query)->sum('debet');
        $totalKredit = (clone $query)->sum('kredit');

        $coa = Coa::find($coaId);
        if ($coa && $coa->coa_position == 'debet') {
            return $totalDebet - $totalKredit;
        }
        return $totalKredit - $totalDebet;
    }

    private function calculateTotalAkun($coaId)
    {
        $coaRepo = new CoaRepo(new Coa(), app(ActivityLogService::class));
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
        return $this->processData($data, $request);
    }

    private function getKasExportData(Request $request, string $category = 'kas')
    {
        if (!$this->isCashAccount($request->input('coa_id'), $category)) {
            return [];
        }

        $total = $this->getFilteredTotal($request);
        $request->page = 0;
        $request->perpage = $total;
        $data = $this->getFilteredData($request);

        return $this->processData($data, $request, $total);
    }

    private function getDetailKasBankData(Request $request, string $category)
    {
        $coaId = $request->input('coa_id');
        if (!$this->isCashAccount($coaId, $category)) {
            return [];
        }

        $fromDate = $request->from_date;
        $untilDate = $request->until_date;

        $mainTransactions = JurnalTransaksi::with('coa')
            ->where('coa_id', $coaId)
            ->whereBetween('transaction_date', [$fromDate, $untilDate])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->orderBy('transaction_no')
            ->get()
            ->groupBy('transaction_no');

        $reportData = [];
        foreach ($mainTransactions as $transactionNo => $mainRows) {
            $counterRows = JurnalTransaksi::with('coa')
                ->where('transaction_no', $transactionNo)
                ->where('coa_id', '!=', $coaId)
                ->orderBy('id')
                ->get();

            $rows = $mainRows->concat($counterRows)->values()->map(function ($row) use ($coaId) {
                return [
                    'id' => $row->id,
                    'transaction_date' => $row->transaction_date,
                    'transaction_no' => $row->transaction_no,
                    'note' => $row->note ?? '',
                    'debet' => (float) $row->debet,
                    'kredit' => (float) $row->kredit,
                    'coa_id' => $row->coa_id,
                    'is_selected_account' => (string) $row->coa_id === (string) $coaId,
                    'coa' => $row->coa,
                ];
            });

            $firstRow = $mainRows->first();
            $reportData[] = [
                'transaction_date' => $firstRow->transaction_date,
                'transaction_no' => $transactionNo,
                'rows' => $rows,
            ];
        }

        return $reportData;
    }

    private function getDetailReportTitle(Request $request, string $label)
    {
        $coa = Coa::find($request->input('coa_id'));
        if (empty($coa)) {
            return 'Laporan Detail ' . $label;
        }

        return 'Laporan Detail ' . $label . ' ' . $coa->coa_name . ' (' . $coa->coa_code . ')';
    }

    private function getReportPeriod(Request $request)
    {
        return 'Periode ' . $request->from_date . ' - ' . $request->until_date;
    }

    private function getCashAccounts(string $category = 'kas')
    {
        $ids = $this->getCashAccountIds($category);
        $query = Coa::where('coa_level', '4')
            ->where('coa_category', $category);

        if (!empty($ids)) {
            $query->orWhere(function ($subQuery) use ($ids) {
                $subQuery->where('coa_level', '4')
                    ->whereIn('id', $ids);
            });
        }

        return $query
            ->orderBy('coa_code')
            ->get();
    }

    private function getCashAccountIds(string $category = 'kas'): array
    {
        $settingValue = SettingRepo::getOptionValue($category === 'bank' ? 'akun_bank' : 'akun_kas');
        if (empty($settingValue)) {
            return [];
        }

        if (is_string($settingValue)) {
            $decoded = json_decode($settingValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $settingValue = $decoded;
            }
        }

        if (is_array($settingValue)) {
            $ids = [];
            array_walk_recursive($settingValue, function ($value, $key) use (&$ids) {
                if (($key === 'id' || is_int($key)) && is_numeric($value)) {
                    $ids[] = (int) $value;
                }
            });
            return array_values(array_unique(array_filter($ids)));
        }

        preg_match_all('/\d+/', (string) $settingValue, $matches);
        return array_values(array_unique(array_map('intval', $matches[0] ?? [])));
    }

    private function isCashAccount($coaId, string $category = 'kas'): bool
    {
        return $this->getCashAccounts($category)
            ->where('id', $coaId)
            ->isNotEmpty();
    }

}
