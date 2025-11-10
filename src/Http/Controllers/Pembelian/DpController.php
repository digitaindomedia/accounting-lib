<?php

namespace Icso\Accounting\Http\Controllers\Pembelian;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\PurchaseDownPaymentExport;
use Icso\Accounting\Exports\PurchaseDpReportExport;
use Icso\Accounting\Http\Requests\CreatePurchaseDpRequest;
use Icso\Accounting\Repositories\Pembelian\Downpayment\DpRepo;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class DpController extends Controller
{
    protected $dpRepo;

    public function __construct(DpRepo $dpRepo)
    {
        $this->dpRepo = $dpRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page ?? 1;
        $perpage = $request->perpage ?? 10;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $vendorId = $request->vendor_id;
        $orderId = $request->order_id;
        $dpType = $request->dp_type;
        $dpStatus = $request->status;

        $whereBetween = !empty($fromDate) && !empty($untilDate) ? [$fromDate, $untilDate] : [];
        $where = [];
        if (!empty($vendorId)) $where[] = ['vendor_id', '=', $vendorId];
        if (!empty($orderId)) $where[] = ['order_id', '=', $orderId];
        if (!empty($dpType)) $where[] = ['dp_type', '=', $dpType];
        if (!empty($dpStatus)) $where[] = ['downpayment_status', '=', $dpStatus];

        return compact('search', 'page', 'perpage', 'whereBetween', 'where','fromDate','untilDate');
    }

    private function formatResponse($data, $total, $page)
    {
        $this->data['status'] = count($data) > 0;
        $this->data['message'] = $this->data['status'] ? 'Data berhasil ditemukan' : 'Data tidak ditemukan';
        $this->data['data'] = $data;
        $this->data['has_more'] = $total > $page;
        $this->data['total'] = $total;
        return response()->json($this->data);
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->dpRepo->getAllDataBetweenBy($search, $page, $perpage, $where, $whereBetween);
        $total = $this->dpRepo->getAllTotalDataBetweenBy($search, $where, $whereBetween);
        $page += count($data);

        return $this->formatResponse($data, $total, $page);
    }

    public function store(CreatePurchaseDpRequest $request)
    {
        $res = $this->dpRepo->store($request);
        $this->data['status'] = (bool) $res;
        $this->data['message'] = $res ? 'Data berhasil disimpan' : 'Data gagal disimpan';
        return response()->json($this->data);
    }

    public function show(Request $request)
    {
        $res = $this->dpRepo->findOne($request->id, [], ['order', 'coa', 'order.vendor', 'tax', 'tax.taxgroup', 'tax.taxgroup.tax']);
        $this->data['status'] = (bool) $res;
        $this->data['message'] = $res ? 'Data berhasil ditemukan' : 'Data gagal disimpan';
        $this->data['data'] = $res ?: '';
        return response()->json($this->data);
    }

    public function delete(Request $request)
    {
        $res = $this->dpRepo->deleteData($request->id);
        $this->data['status'] = (bool) $res;
        $this->data['message'] = $res ? 'Data berhasil dihapus' : 'Data gagal dihapus';
        return response()->json($this->data);
    }

    public function deleteAll(Request $request)
    {
        $reqData = json_decode(json_encode($request->ids));
        $successDelete = 0;
        $failedDelete = 0;

        foreach ($reqData as $id) {
            $res = $this->dpRepo->deleteData($id);
            $res ? $successDelete++ : $failedDelete++;
        }

        $this->data['status'] = $successDelete > 0;
        $this->data['message'] = $successDelete > 0 ? "$successDelete Data berhasil dihapus <br /> $failedDelete Data tidak bisa dihapus" : 'Data gagal dihapus';
        $this->data['data'] = [];
        return response()->json($this->data);
    }

    private function prepareExportData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $total = $this->dpRepo->getAllTotalDataBetweenBy($search, $where, $whereBetween);
        return $this->dpRepo->getAllDataBetweenBy($search, $page, $total, $where, $whereBetween);
    }

    public function export(Request $request)
    {
       return $this->exportAsFormat($request,'uang-muka-pembelian.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request,'uang-muka-pembelian.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchaseDownPaymentExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchaseDownPaymentExport($data);
        $pdf = PDF::loadView('accounting::purchase.purchase_downpayment_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('uang-muka-pembelian.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->dpRepo->getAllDataBetweenBy($search, $page, $perpage, $where, $whereBetween);
        return Excel::download(new PurchaseDpReportExport($data,$params), 'laporan-uang-muka-pembelian.xlsx');

    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->dpRepo->getAllDataBetweenBy($search, $page, $perpage, $where, $whereBetween);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::purchase.purchase_downpayment_detail_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-uang-muka-pembelian.pdf');
        }

        return $pdf->download('laporan-uang-muka-pembelian.pdf');

    }

}
