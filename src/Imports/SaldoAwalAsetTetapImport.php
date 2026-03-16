<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseOrder;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\OrderRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class SaldoAwalAsetTetapImport implements ToCollection
{
    protected $userId;
    protected $orderRepo;
    private $errors = [];
    private $success = [];
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->orderRepo = new OrderRepo(new PurchaseOrder());
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            if ($this->isEmptyRow($row)) {
                continue;
            }

            $this->totalRows++;

            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }

            $asetDate = Helpers::formatDateExcel($row[0]);
            $namaAset = trim($row[1]);
            $nilaiBeli = Utility::remove_commas($row[2]);
            $coaAset = Coa::where('coa_code', trim($row[3]))->first();
            $coaAkumulasi = Coa::where('coa_code', trim($row[4]))->first();
            $coaPenyusutan = Coa::where('coa_code', trim($row[5]))->first();
            $persentase = $this->normalizeNumericValue($row[6] ?? null);
            $masaManfaat = $this->normalizeNumericValue($row[7] ?? null);
            $note = $row[8] ?? '';

            $req = new Request();
            $req->user_id = $this->userId;
            $req->nama_aset = $namaAset;
            $req->aset_tetap_date = $asetDate;
            $req->tanggal_input_aset = $asetDate;
            $req->harga_beli = $nilaiBeli;
            $req->aset_tetap_coa_id = $coaAset->id;
            $req->akumulasi_penyusutan_coa_id = $coaAkumulasi->id;
            $req->penyusutan_coa_id = $coaPenyusutan->id;
            $req->note = $note;
            $req->qty = 1;
            $req->is_saldo_awal = 1;
            $req->status_penyusutan = 1;

            if (!empty($masaManfaat)) {
                $req->pilihan_nilai = 'masa';
                $req->masa_manfaat = $masaManfaat;
                $req->nilai_penyusutan = 0;
            } else {
                $req->pilihan_nilai = 'persen';
                $req->masa_manfaat = 0;
                $req->nilai_penyusutan = $persentase;
            }

            try {
                $res = $this->orderRepo->store($req);
                if ($res) {
                    $this->successCount++;
                    $this->success[] = 'Baris ' . ($index + 1) . ': Berhasil disimpan.';
                } else {
                    $this->errors[] = 'Baris ' . ($index + 1) . ': Gagal disimpan.';
                }
            } catch (\Exception $e) {
                $this->errors[] = 'Baris ' . ($index + 1) . ': Error: ' . $e->getMessage();
            }
        }
    }

    private function hasValidationErrors($index, $row): bool
    {
        if (empty($row[0])) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Tanggal Perolehan kosong.';
            return true;
        }

        if (empty($row[1])) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Nama Aset kosong.';
            return true;
        }

        if ($this->normalizeNumericValue($row[2] ?? null) <= 0) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Nilai Beli harus lebih besar dari 0.';
            return true;
        }

        if (empty($row[3])) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Aset kosong.';
            return true;
        }

        if (!Coa::where('coa_code', trim($row[3]))->exists()) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Aset tidak ditemukan.';
            return true;
        }

        if (empty($row[4])) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Akumulasi Penyusutan kosong.';
            return true;
        }

        if (!Coa::where('coa_code', trim($row[4]))->exists()) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Akumulasi Penyusutan tidak ditemukan.';
            return true;
        }

        if (empty($row[5])) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Penyusutan kosong.';
            return true;
        }

        if (!Coa::where('coa_code', trim($row[5]))->exists()) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Penyusutan tidak ditemukan.';
            return true;
        }

        $persentase = $this->normalizeNumericValue($row[6] ?? null);
        $masaManfaat = $this->normalizeNumericValue($row[7] ?? null);

        if ($persentase <= 0 && $masaManfaat <= 0) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Isi Persentase Penyusutan atau Masa Manfaat.';
            return true;
        }

        if ($persentase > 0 && $masaManfaat > 0) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Pilih salah satu, Persentase Penyusutan atau Masa Manfaat.';
            return true;
        }

        return false;
    }

    private function isEmptyRow($row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeNumericValue($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) Utility::remove_commas($value);
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
