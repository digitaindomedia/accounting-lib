<?php

namespace Icso\Accounting\Repositories\Penjualan\Payment;


use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentInvoice;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo;
use Illuminate\Http\Request;

class PaymentInvoiceRepo extends ElequentRepository
{

    protected $model;

    public function __construct(SalesPaymentInvoice $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }
    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('payment_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('payment_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $paymentDate = $request->payment_date;
        $id = $request->id;
        $invoiceNo = $request->invoice_no;
        $invoiceId = $request->invoice_id;
        $totalPayment = $request->total_payment;
        $jurnalAkunId = $request->jurnal_akun_id;
        $jurnalId = $request->jurnal_id;
        $paymentId = !empty($request->payment_id) ? $request->payment_id : '0';
        $vendorId = !empty($request->vendor_id) ? $request->vendor_id : '0';
        $returId = !empty($request->retur_id) ? $request->retur_id : '0';
        $discountTotal = !empty($request->discount_total) ? $request->discount_total : '0';
        $coaIdDiscount = !empty($request->coa_id_discount) ? $request->coa_id_discount : '';
        $overPaymentTotal = !empty($request->overpayment_total) ? $request->overpayment_total : '0';
        $coaIdOverpayment = !empty($request->coa_id_overpayment) ? $request->coa_id_overpayment : '';
        $paymentNo = !empty($request->payment_no) ? $request->payment_no : '';
        $arrData = array(
            'invoice_no' => $invoiceNo,
            'invoice_id' => $invoiceId,
            'total_payment' => $totalPayment,
            'payment_date' => $paymentDate,
            'vendor_id' => $vendorId,
            'jurnal_akun_id' => $jurnalAkunId,
            'total_discount' => $discountTotal,
            'coa_id_discount' => $coaIdDiscount,
            'payment_id' => $paymentId,
            'jurnal_id' => $jurnalId,
            'total_overpayment' => $overPaymentTotal,
            'coa_id_overpayment' => $coaIdOverpayment,
            'retur_id' => $returId,
            'payment_no' => $paymentNo,
        );
        $res = null;
        if(empty($id)) {
            $res = $this->create($arrData);
            InvoiceRepo::changeStatusInvoice($invoiceId);
        } else {
            $res = $this->update($arrData, $id);
        }
        return $res;
    }

    public function getAllPaymentByInvoiceId($idInvoice)
    {
        // TODO: Implement getAllPaymentByInvoiceId() method.
        $res = $this->findAllByWhere(array('invoice_id' => $idInvoice));
        $total = 0;
        if(count($res) > 0)
        {
            foreach ($res as $re)
            {
                $pay = ($re->total_payment + $re->total_discount) - $re->total_overpayment;
                $total = $total + $pay;
            }
        }
        return $total;
    }

    public static function sumGrandTotalByVendor($vendorId, $dari, $sampai='', $sign='between'){
        if($sign == 'between') {
            $totalPayment = SalesPaymentInvoice::where([['vendor_id', '=', $vendorId]])->whereBetween('payment_date',[$dari,$sampai])->sum('total_payment');
            $totalDiscount = SalesPaymentInvoice::where([['vendor_id', '=', $vendorId], ['coa_id_discount', "!=", ""]])->whereBetween('payment_date',[$dari,$sampai])->sum('total_discount');
            $totalOverPayment = SalesPaymentInvoice::where([['vendor_id', '=', $vendorId], ['coa_id_overpayment', "!=", ""]])->whereBetween('payment_date',[$dari,$sampai])->sum('total_overpayment');
            $total = ($totalPayment + $totalDiscount) - $totalOverPayment;
        } else{
            $totalPayment = SalesPaymentInvoice::where([['vendor_id', '=', $vendorId],['payment_date', $sign, $dari]])->sum('total_payment');
            $totalDiscount = SalesPaymentInvoice::where([['vendor_id', '=', $vendorId], ['coa_id_discount', "!=", ""],['payment_date', $sign, $dari]])->sum('total_discount');
            $totalOverPayment = SalesPaymentInvoice::where([['vendor_id', '=', $vendorId], ['coa_id_overpayment', "!=", ""],['payment_date', $sign, $dari]])->sum('total_overpayment');
            $total = ($totalPayment + $totalDiscount) - $totalOverPayment;
        }
        return $total;
    }
}
