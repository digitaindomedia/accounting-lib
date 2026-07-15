<?php

namespace Icso\Accounting\Http\Controllers\Akuntansi;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\LabaRugiExport;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class LabaRugiController extends Controller
{
    protected $jurnalTransaksiRepo;

    public function __construct(JurnalTransaksiRepo $jurnalTransaksiRepo)
    {
        $this->jurnalTransaksiRepo = $jurnalTransaksiRepo;
    }

    public function show(Request $request){
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $res = JurnalTransaksiRepo::labaRugi($fromDate,$untilDate);
        if(!empty($res)){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function dashboard(Request $request)
    {
        $year = (int) ($request->year ?: Carbon::now()->year);
        $now = Carbon::now();
        $months = [];
        $yearToDate = [
            'pendapatan' => 0,
            'biaya_operasional' => 0,
            'other' => 0,
            'ebt' => 0,
        ];

        for ($month = 1; $month <= 12; $month++) {
            $fromDate = Carbon::create($year, $month, 1)->startOfMonth();
            $untilDate = Carbon::create($year, $month, 1)->endOfMonth();

            if ($year === (int) $now->year && $month > (int) $now->month) {
                $data = $this->emptyLabaRugiData();
            } else {
                $data = JurnalTransaksiRepo::labaRugi($fromDate->toDateString(), $untilDate->toDateString());
            }

            $summary = $this->summarizeLabaRugi($data);
            $yearToDate['pendapatan'] += $summary['pendapatan'];
            $yearToDate['biaya_operasional'] += $summary['biaya_operasional'];
            $yearToDate['other'] += $summary['other'];
            $yearToDate['ebt'] += $summary['ebt'];

            $months[] = [
                'month' => $month,
                'month_name' => $fromDate->translatedFormat('M'),
                'from_date' => $fromDate->toDateString(),
                'until_date' => $untilDate->toDateString(),
                ...$summary,
            ];
        }

        $currentFromDate = Carbon::create($year, (int) $now->month, 1)->startOfMonth();
        $currentUntilDate = Carbon::create($year, (int) $now->month, 1)->endOfMonth();
        $currentMonth = $year === (int) $now->year
            ? $this->summarizeLabaRugi(JurnalTransaksiRepo::labaRugi($currentFromDate->toDateString(), $currentUntilDate->toDateString()))
            : $this->emptySummary();

        return response()->json([
            'status' => true,
            'message' => 'Data dashboard laba rugi berhasil ditemukan',
            'data' => [
                'year' => $year,
                'current_month' => [
                    'month' => (int) $now->month,
                    'month_name' => $now->translatedFormat('F'),
                    'from_date' => $currentFromDate->toDateString(),
                    'until_date' => $currentUntilDate->toDateString(),
                    ...$currentMonth,
                ],
                'year_to_date' => $yearToDate,
                'months' => $months,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $data = $this->prepareLabaRugiData($fromDate, $untilDate);
        return Excel::download(new LabaRugiExport($data), 'labarugi.xlsx');
    }

    public function exportToPdf(Request $request)
    {
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $data = $this->prepareLabaRugiData($fromDate, $untilDate);
        $export = new LabaRugiExport($data);
        $data = $export->getData();
        $pdf = PDF::loadView('accounting::laba_rugi_pdf', ['data' => $data]);
        return $pdf->download('labarugi.pdf');

    }

    private function prepareLabaRugiData($fromDate, $untilDate)
    {
        $labaRugiData = $this->jurnalTransaksiRepo->labaRugi($fromDate, $untilDate);
        $data = [];

        foreach (['pendapatan', 'biaya_operasional', 'other'] as $category) {
            foreach ($labaRugiData[$category]['coa'] as $coa) {
                $prefix = str_repeat(' ', 4);
                $data[] = [
                    'coa_name' => !empty($coa['coa_code']) ? $prefix . $coa['coa_name'] : $coa['coa_name'] ?? '',
                    'saldo' => $coa['saldo'] ?? 0
                ];
            }
        }

        $ebt = $labaRugiData['ebt'] ?? 0;
        $data[] = [
            'coa_name' => $ebt < 0 ? 'Rugi Bersih' : 'Laba Bersih',
            'saldo' => abs($ebt)
        ];

        return $data;
    }

    private function summarizeLabaRugi(array $data): array
    {
        return [
            'pendapatan' => (float) ($data['pendapatan']['total'] ?? 0),
            'biaya_operasional' => (float) ($data['biaya_operasional']['total'] ?? 0),
            'other' => (float) ($data['other']['total'] ?? 0),
            'ebt' => (float) ($data['ebt'] ?? 0),
        ];
    }

    private function emptyLabaRugiData(): array
    {
        return [
            'pendapatan' => ['total' => 0],
            'biaya_operasional' => ['total' => 0],
            'other' => ['total' => 0],
            'ebt' => 0,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'pendapatan' => 0,
            'biaya_operasional' => 0,
            'other' => 0,
            'ebt' => 0,
        ];
    }
}
