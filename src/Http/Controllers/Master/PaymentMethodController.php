<?php
namespace Icso\Accounting\Http\Controllers\Master;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\PaymentMetodExport;
use Icso\Accounting\Http\Requests\CreatePaymentRequest;
use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Repositories\Master\PaymentMethodRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class PaymentMethodController extends Controller
{
    protected $paymentRepo;

    public function __construct(PaymentMethodRepo $paymentRepo)
    {
        $this->paymentRepo = $paymentRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        return compact('search', 'page', 'perpage');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->paymentRepo->getAllDataBy($search,$page,$perpage);
        $total = $this->paymentRepo->getAllTotalDataBy($search);
        $has_more = false;
        $page = $page + count($data);
        if($total > $page)
        {
            $has_more = true;
        }
        if($data)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $has_more;
            $this->data['total'] = $total;
        } else{
            $this->data['status'] = false;
            $this->data['has_more'] = $has_more;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function store(CreatePaymentRequest $request)
    {
        $res = $this->paymentRepo->store($request);
        if($res)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';

        }else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
        }
        return response()->json($this->data);
    }

    public function show(Request $request)
    {
        $id = $request->id;
        $data = $this->paymentRepo->findOne($id);
        if($data)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'data berhasil ditemukan';
            $this->data['data'] = $data;

        }else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal ditemukan';
        }
        return response()->json($this->data);

    }
    public function destroy(Request $request)
    {
        $id = $request->id;
        try
        {
            $data = PaymentMethod::find($id);
            if($data)
            {
                if($data->canDelete()){
                    $data->delete();
                    $this->data['status'] = true;
                    $this->data['message'] = 'Data berhasil dihapus ';
                } else {
                    $this->data['status'] = false;
                    $this->data['message'] = 'Data tidak bisa dihapus';
                }


            }else{
                $this->data['status'] = false;
                $this->data['message'] = 'Data gagal dihapus';
            }
        } catch (\Exception $e) {
            $this->data['status'] = false;
            $this->data['message'] = 'Terjadi kesalahan dalam hapus data';
        }
        return response()->json($this->data);
    }

    public function deleteAll(Request $request)
    {
        $reqData = $request->ids;
        $successDelete = 0;
        $failedDelete = 0;
        if(count($reqData) > 0){
            foreach ($reqData as $res){
                $id = $res['id'];
                $data = PaymentMethod::find($id);
                if($data)
                {
                    if($data->canDelete()) {
                        $data->delete();
                        $successDelete = $successDelete + 1;
                    }
                    else {
                        $failedDelete = $failedDelete + 1;
                    }
                } else {
                    $failedDelete = $failedDelete + 1;
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

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'pembayaran.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'pembayaran.csv');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getPaymentData($request);

        $pdf = Pdf::loadView('accounting::master.payment', ['arrData' => $data]);
        return $pdf->download('payment.pdf');
    }

    /* ---------- Shared Methods ---------- */

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->getPaymentData($request);
        return Excel::download(new PaymentMetodExport($data), $filename);
    }

    public function getPaymentData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params); // $search, $page, $categoryType

        return $this->paymentRepo->getAllDataBy($search, $page, 100);
    }
}
