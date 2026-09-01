<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Enums\InvoiceStatusEnum;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\Utility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class JurnalPurchaseInvoiceImport implements ToCollection
{
    protected $userId;
    protected $coaId;
    private $errors = [];
    private $success = [];
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId, $coaId = null)
    {
        $this->userId = $userId;
        $this->coaId = $coaId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row) || $this->isHeaderRow($row)) {
                continue;
            }
            $this->totalRows++;

            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }

            $invoiceNo = trim((string) $row[0]);
            $invoiceDate = Helpers::formatDateExcel($row[1]);
            $vendorCode = trim((string) $row[2]);
            $coaCode = trim((string) $row[3]);
            $note = trim((string) ($row[4] ?? ''));
            $nominal = Utility::remove_commas(trim((string) $row[5]));

            $vendor = Vendor::where('vendor_code', $vendorCode)->first();
            $coa = !empty($this->coaId)
                ? Coa::find($this->coaId)
                : Coa::where('coa_code', $coaCode)->first();

            try {
                $storedId = $this->storeSaldoAwalInvoice($invoiceNo, $invoiceDate, $note, $vendor->id, $coa->id, $nominal);
                $invoice = $storedId ? $this->findImportedInvoiceById($storedId) : null;

                Log::info('[IMP-v2] baris=' . ($index + 1) . ' id=' . ($storedId ?? 'null') . ' found=' . ($invoice ? 'true' : 'false') . ' inv=' . $invoiceNo);

                if ($storedId && $invoice) {
                    $this->successCount++;
                    $this->success[] = "Baris " . ($index + 1) . ": Berhasil disimpan.";
                } else {
                    $reason = $storedId
                        ? "Invoice ID {$storedId} tidak ditemukan setelah store sukses."
                        : 'Insert invoice tidak mengembalikan ID.';

                    $this->recordFailedRow($index, $invoiceNo, $invoiceDate, $vendorCode, $coa->coa_code ?? $coaCode, $nominal, $reason);
                }
            } catch (\Exception $e) {
                Log::warning('[JurnalPurchaseInvoiceImport] exception baris ' . ($index + 1) . ': ' . $e->getMessage());
                $this->errors[] = "Baris " . ($index + 1) . ": Error: " . $e->getMessage();
            }
        }
    }

    private function isEmptyRow($row)
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function isHeaderRow($row)
    {
        $headers = [
            0 => ['nomor invoice', 'no invoice'],
            1 => ['tanggal invoice'],
            2 => ['kode vendor'],
            3 => ['kode akun', 'kode coa', 'akun (coa)'],
            5 => ['nominal'],
        ];

        $matchedHeaders = 0;

        foreach ($headers as $columnIndex => $expectedHeaders) {
            $value = strtolower(trim((string) ($row[$columnIndex] ?? '')));

            foreach ($expectedHeaders as $expectedHeader) {
                if ($value !== '' && strpos($value, $expectedHeader) !== false) {
                    $matchedHeaders++;
                    break;
                }
            }
        }

        return $matchedHeaders >= count($headers);
    }

    private function normalizedCell($row, $index)
    {
        return trim((string) ($row[$index] ?? ''));
    }

    private function findImportedInvoiceById($id)
    {
        return DB::table((new PurchaseInvoicing())->getTable())
            ->useWritePdo()
            ->where('id', $id)
            ->first();
    }

    private function storeSaldoAwalInvoice($invoiceNo, $invoiceDate, $note, $vendorId, $coaId, $nominal)
    {
        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $id = DB::table((new PurchaseInvoicing())->getTable())->insertGetId([
                'invoice_no' => $invoiceNo,
                'invoice_date' => $invoiceDate,
                'due_date' => date('Y-m-d'),
                'order_id' => 0,
                'invoice_status' => InvoiceStatusEnum::BELUM_LUNAS,
                'vendor_id' => $vendorId,
                'dp_nominal' => 0,
                'note' => $note,
                'subtotal' => $nominal,
                'discount' => 0,
                'discount_type' => '',
                'discount_total' => 0,
                'tax_total' => 0,
                'tax_type' => '',
                'dpp_total' => 0,
                'grandtotal' => $nominal,
                'invoice_type' => ProductType::ITEM,
                'input_type' => InputType::SALDO_AWAL,
                'coa_id' => $coaId,
                'jurnal_id' => 0,
                'warehouse_id' => 0,
                'created_at' => now(),
                'created_by' => $this->userId,
                'updated_at' => now(),
                'updated_by' => $this->userId,
            ]);

            DB::commit();

            return $id;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function recordFailedRow($index, $invoiceNo, $invoiceDate, $vendorCode, $coaCode, $nominal, $reason = null)
    {
        $message = "Baris " . ($index + 1) . ": Gagal disimpan. Invoice: {$invoiceNo}, tanggal: {$invoiceDate}, vendor: {$vendorCode}, coa: {$coaCode}, nominal: {$nominal}.";

        if (!empty($reason)) {
            $message .= " Penyebab: {$reason}.";
        }

        $this->errors[] = $message;

        Log::warning('[JurnalPurchaseInvoiceImport] ' . $message);
    }

    private function hasValidationErrors($index, $row)
    {
        $invoiceNo = $this->normalizedCell($row, 0);
        $invoiceDate = $this->normalizedCell($row, 1);
        $vendorCode = $this->normalizedCell($row, 2);
        $coaCode = $this->normalizedCell($row, 3);
        $nominal = $this->normalizedCell($row, 5);

        if ($invoiceNo === '') {
            $this->errors[] = "Baris " . ($index + 1) . ": Nomor Invoice Kosong.";
            return true;
        }
        if ($invoiceDate === '') {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal Invoice Kosong.";
            return true;
        }
        if ($vendorCode === '') {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Vendor Kosong.";
            return true;
        }
        if (!Vendor::where('vendor_code', $vendorCode)->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Vendor tidak ditemukan.";
            return true;
        }
        if (!empty($this->coaId)) {
            if (!Coa::where('id', $this->coaId)->exists()) {
                $this->errors[] = "Baris " . ($index + 1) . ": Akun (COA) import tidak ditemukan.";
                return true;
            }
        } elseif ($coaCode === '') {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Akun (COA) Kosong.";
            return true;
        } elseif (!Coa::where('coa_code', $coaCode)->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Akun (COA) tidak ditemukan.";
            return true;
        }
        if ($nominal === '') {
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
