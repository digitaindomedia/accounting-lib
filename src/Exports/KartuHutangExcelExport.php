<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePaymentInvoice;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Pembelian\Payment\PaymentInvoiceRepo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use DB;
class KartuHutangExcelExport implements FromView
{
    protected $vendorId;
    protected $fromDate;
    protected $untilDate;

    public function __construct($vendorId, $fromDate, $untilDate)
    {
        $this->vendorId = $vendorId;
        $this->fromDate = $fromDate;
        $this->untilDate = $untilDate;
    }

    public function view(): View
    {
        if ($this->vendorId) {
            $vendors = Vendor::where('id', $this->vendorId)->get();
        }
        else {
            // Kosong → ambil semua vendor supplier
            $vendors = Vendor::where('vendor_type', 'supplier')->get();
        }

        $hasil = [];

        foreach ($vendors as $vendor) {

            // Hitung saldo awal
            $saldoAwalInvoice = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $this->fromDate, $this->untilDate, "<");
            $saldoAwalPelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $this->fromDate, $this->untilDate, "<");
            $saldoAwal = $saldoAwalInvoice - $saldoAwalPelunasan;

            // Ambil transaksi
            $resultInvoice = PurchaseInvoicing::select(
                'invoice_date as tanggal',
                'invoice_no as nomor',
                DB::raw("'Pembelian' as note"),
                DB::raw("'0' as debet"),
                'grandtotal as kredit'
            )
                ->where('vendor_id', $vendor->id)
                ->whereBetween('invoice_date', [$this->fromDate, $this->untilDate]);

            $resultPayment = PurchasePaymentInvoice::select(
                'payment_date as tanggal',
                'payment_no as nomor',
                DB::raw("'Pelunasan' as note"),
                DB::raw('(total_payment + total_discount) - total_overpayment as debet'),
                DB::raw("'0' as kredit")
            )
                ->where('vendor_id', $vendor->id)
                ->whereBetween('payment_date', [$this->fromDate, $this->untilDate]);

            $transaksi = $resultInvoice
                ->union($resultPayment)
                ->orderBy('tanggal', 'asc')
                ->get();

            // Hitung saldo berjalan
            $runningSaldo = $saldoAwal;
            foreach ($transaksi as $t) {
                $runningSaldo = $runningSaldo + $t->kredit - $t->debet;
                $t->saldo = $runningSaldo;
            }
            $hasil[] = [
                'vendor' => $vendor,
                'saldoAwal' => $saldoAwal,
                'transaksi' => $transaksi
            ];
        }
        return view('accounting::purchase.kartu_hutang_report', [
            'listVendor' => $hasil
        ]);
    }
}