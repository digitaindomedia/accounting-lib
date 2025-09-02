<?php

namespace Icso\Accounting\Http\Controllers\Akuntansi;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\LabaRugiExport;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
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
        $pdf = PDF::loadView('laba_rugi_pdf', ['data' => $data]);
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
                    'saldo' => !empty($coa['saldo']) ? number_format($coa['saldo'], 2) : '0'
                ];
            }
        }

        return $data;
    }
}
