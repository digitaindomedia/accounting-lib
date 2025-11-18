<?php

namespace Icso\Accounting\Exports;


use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentInvoice;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentInvoiceRepo;
use Icso\Accounting\Utils\VendorType;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use DB;
class KartuPiutangExcelExport implements FromView
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
            $vendors = Vendor::where('vendor_type', VendorType::CUSTOMER)->get();
        }

        $hasil = [];

        foreach ($vendors as $vendor) {

            // Hitung saldo awal
            $saldoAwalInvoice = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $this->fromDate, $this->untilDate, "<");
            $saldoAwalPelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $this->fromDate, $this->untilDate, "<");
            $saldoAwal = $saldoAwalInvoice - $saldoAwalPelunasan;

            // Ambil transaksi
            $resultInvoice = SalesInvoicing::select(
                'invoice_date as tanggal',
                'invoice_no as nomor',
                DB::raw("'Penjualan' as note"),
                'grandtotal as debet',
                DB::raw("'0' as kredit")
            )
                ->where('vendor_id', $vendor->id)
                ->whereBetween('invoice_date', [$this->fromDate, $this->untilDate]);

            $resultPayment = SalesPaymentInvoice::select(
                'payment_date as tanggal',
                'payment_no as nomor',
                DB::raw("'Pelunasan' as note"),
                DB::raw("'0' as debet"),
                DB::raw('(total_payment + total_discount) - total_overpayment as kredit'),

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
                $runningSaldo = $runningSaldo + $t->debet - $t->kredit;
                $t->saldo = $runningSaldo;
            }
            $hasil[] = [
                'vendor' => $vendor,
                'saldoAwal' => $saldoAwal,
                'transaksi' => $transaksi
            ];
        }
        return view('accounting::sales.kartu_piutang_report', [
            'listVendor' => $hasil
        ]);
    }
}