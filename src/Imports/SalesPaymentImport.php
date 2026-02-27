<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPayment;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\VendorType;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SalesPaymentImport implements ToCollection
{
    protected $userId;
    protected $paymentRepo;
    private $errors = [];
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->paymentRepo = new PaymentRepo(new SalesPayment());
    }

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        $groupedPayments = [];

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $this->totalRows++;

            $paymentNo = trim((string)($row[0] ?? ''));
            $paymentDateRaw = $row[1] ?? '';
            $customerCode = trim((string)($row[2] ?? ''));
            $paymentCoaCode = trim((string)($row[3] ?? ''));
            $invoiceNo = trim((string)($row[4] ?? ''));
            $nominalRaw = $row[5] ?? '';
            $note = trim((string)($row[6] ?? ''));

            $validation = $this->validateRow(
                $index,
                $paymentNo,
                $paymentDateRaw,
                $customerCode,
                $paymentCoaCode,
                $invoiceNo,
                $nominalRaw
            );

            if ($validation === null) {
                continue;
            }

            $rowNo = $index + 1;
            $paymentDate = $validation['payment_date'];
            $vendor = $validation['vendor'];
            $paymentMethod = $validation['payment_method'];
            $invoice = $validation['invoice'];
            $nominal = $validation['nominal'];

            if (!isset($groupedPayments[$paymentNo])) {
                $groupedPayments[$paymentNo] = [
                    'payment_no' => $paymentNo,
                    'payment_date' => $paymentDate,
                    'vendor_id' => $vendor->id,
                    'payment_method_id' => $paymentMethod->id,
                    'note' => $note,
                    'total' => 0,
                    'invoice' => [],
                    'row_no' => $rowNo,
                ];
            } else {
                $group = $groupedPayments[$paymentNo];

                if ($group['vendor_id'] !== $vendor->id) {
                    $this->errors[] = "Baris {$rowNo}: No pembayaran {$paymentNo} memiliki kode customer berbeda.";
                    continue;
                }

                if ($group['payment_method_id'] !== $paymentMethod->id) {
                    $this->errors[] = "Baris {$rowNo}: No pembayaran {$paymentNo} memiliki kode COA pembayaran berbeda.";
                    continue;
                }

                if ($group['payment_date'] !== $paymentDate) {
                    $this->errors[] = "Baris {$rowNo}: No pembayaran {$paymentNo} memiliki tanggal berbeda.";
                    continue;
                }

                if (empty($group['note']) && !empty($note)) {
                    $groupedPayments[$paymentNo]['note'] = $note;
                }
            }

            $groupedPayments[$paymentNo]['invoice'][] = [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'vendor_id' => $vendor->id,
                'nominal_paid' => $nominal,
            ];
            $groupedPayments[$paymentNo]['total'] += $nominal;
        }

        foreach ($groupedPayments as $paymentNo => $group) {
            if (empty($group['invoice'])) {
                continue;
            }

            $request = new Request();
            $request->payment_no = $group['payment_no'];
            $request->payment_date = $group['payment_date'];
            $request->vendor_id = $group['vendor_id'];
            $request->payment_method_id = $group['payment_method_id'];
            $request->note = $group['note'];
            $request->total = $group['total'];
            $request->invoice = $group['invoice'];
            $request->retur = [];
            $request->user_id = $this->userId;

            $saved = $this->paymentRepo->store($request);
            if ($saved) {
                $this->successCount++;
            } else {
                $this->errors[] = "No pembayaran {$paymentNo} gagal import (cek validasi jurnal atau data invoice).";
            }
        }
    }

    private function validateRow(
        int $index,
        string $paymentNo,
        $paymentDateRaw,
        string $customerCode,
        string $paymentCoaCode,
        string $invoiceNo,
        $nominalRaw
    ): ?array {
        $rowNo = $index + 1;

        if ($paymentNo === '') {
            $this->errors[] = "Baris {$rowNo}: No pembayaran kosong.";
            return null;
        }

        if (SalesPayment::where('payment_no', $paymentNo)->exists()) {
            $this->errors[] = "Baris {$rowNo}: No pembayaran {$paymentNo} sudah ada.";
            return null;
        }

        if ($paymentDateRaw === '' || $paymentDateRaw === null) {
            $this->errors[] = "Baris {$rowNo}: Tanggal kosong.";
            return null;
        }

        $paymentDate = $this->parseDate($paymentDateRaw);
        if (empty($paymentDate)) {
            $this->errors[] = "Baris {$rowNo}: Format tanggal tidak valid, gunakan format thn-bulan-tgl.";
            return null;
        }

        if ($customerCode === '') {
            $this->errors[] = "Baris {$rowNo}: Kode customer kosong.";
            return null;
        }

        $vendor = Vendor::where('vendor_code', $customerCode)
            ->where('vendor_type', VendorType::CUSTOMER)
            ->first();
        if (empty($vendor)) {
            $this->errors[] = "Baris {$rowNo}: Kode customer {$customerCode} tidak ditemukan.";
            return null;
        }

        if ($paymentCoaCode === '') {
            $this->errors[] = "Baris {$rowNo}: Kode COA pembayaran kosong.";
            return null;
        }

        $coa = Coa::where('coa_code', $paymentCoaCode)->first();
        if (empty($coa)) {
            $this->errors[] = "Baris {$rowNo}: Kode COA pembayaran {$paymentCoaCode} tidak ditemukan.";
            return null;
        }

        $paymentMethod = PaymentMethod::where('coa_id', $coa->id)->first();
        if (empty($paymentMethod)) {
            $this->errors[] = "Baris {$rowNo}: Tidak ada metode pembayaran dengan COA {$paymentCoaCode}.";
            return null;
        }

        if ($invoiceNo === '') {
            $this->errors[] = "Baris {$rowNo}: No invoice kosong.";
            return null;
        }

        $invoice = SalesInvoicing::where('invoice_no', $invoiceNo)->first();
        if (empty($invoice)) {
            $this->errors[] = "Baris {$rowNo}: No invoice {$invoiceNo} tidak ditemukan.";
            return null;
        }

        if ((int)$invoice->vendor_id !== (int)$vendor->id) {
            $this->errors[] = "Baris {$rowNo}: Invoice {$invoiceNo} bukan milik customer {$customerCode}.";
            return null;
        }

        if ($nominalRaw === '' || $nominalRaw === null) {
            $this->errors[] = "Baris {$rowNo}: Nominal pembayaran kosong.";
            return null;
        }

        $nominal = (float) str_replace(',', '', (string)$nominalRaw);
        if ($nominal <= 0) {
            $this->errors[] = "Baris {$rowNo}: Nominal pembayaran harus lebih dari 0.";
            return null;
        }

        return [
            'payment_date' => $paymentDate,
            'vendor' => $vendor,
            'payment_method' => $paymentMethod,
            'invoice' => $invoice,
            'nominal' => $nominal,
        ];
    }

    private function parseDate($value): string
    {
        if (is_numeric($value)) {
            return Helpers::formatDateExcel($value);
        }

        $parsed = date('Y-m-d', strtotime((string)$value));
        if ($parsed === '1970-01-01' && trim((string)$value) !== '1970-01-01') {
            return '';
        }
        return $parsed;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getTotalRows()
    {
        return $this->totalRows;
    }
}
