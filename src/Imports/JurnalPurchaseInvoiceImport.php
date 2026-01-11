<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;

class JurnalPurchaseInvoiceImport implements ToCollection
{
    protected $userId;
    private $errors = [];
    private $success = [];
    protected $invoiceRepo;
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->invoiceRepo = new InvoiceRepo(new PurchaseInvoicing());
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $this->totalRows++;

            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }

            $invoiceNo = $row[0];
            $invoiceDate = Helpers::formatDateExcel($row[1]);
            $vendorCode = $row[2];
            $coaCode = $row[3];
            $note = $row[4];
            $nominal = $row[5];

            $vendor = Vendor::where('vendor_code', $vendorCode)->first();
            $coa = Coa::where('coa_code', $coaCode)->first();

            $req = new Request();
            $req->coa_id = $coa->id;
            $req->user_id = $this->userId;
            $req->invoice_date = $invoiceDate;
            $req->invoice_no = $invoiceNo;
            $req->note = $note;
            $req->vendor_id = $vendor->id;
            $req->subtotal = Utility::remove_commas($nominal);
            $req->grandtotal = Utility::remove_commas($nominal);
            $req->invoice_type = ProductType::ITEM;
            $req->input_type = InputType::JURNAL;

            try {
                $res = $this->invoiceRepo->store($req);
                if ($res) {
                    $this->successCount++;
                    $this->success[] = "Baris " . ($index + 1) . ": Berhasil disimpan.";
                } else {
                    $this->errors[] = "Baris " . ($index + 1) . ": Gagal disimpan.";
                }
            } catch (\Exception $e) {
                $this->errors[] = "Baris " . ($index + 1) . ": Error: " . $e->getMessage();
            }
        }
    }

    private function hasValidationErrors($index, $row)
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Nomor Invoice Kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal Invoice Kosong.";
            return true;
        }
        if (empty($row[2])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Vendor Kosong.";
            return true;
        }
        if (!Vendor::where('vendor_code', $row[2])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Vendor tidak ditemukan.";
            return true;
        }
        if (empty($row[3])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Akun (COA) Kosong.";
            return true;
        }
        if (!Coa::where('coa_code', $row[3])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Akun (COA) tidak ditemukan.";
            return true;
        }
        if (empty($row[5])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Nominal Kosong.";
            return true;
        }
        return false;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccess()
    {
        return $this->success;
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
