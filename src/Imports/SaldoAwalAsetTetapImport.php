<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseOrder;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\OrderRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\Utility;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;

class SaldoAwalAsetTetapImport implements ToCollection
{
    protected $userId;
    protected $coaId;
    protected $coaAsetSaldoAwal;
    private $errors = [];
    private $success = [];
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId, $coaId)
    {
        $this->userId = $userId;
        $this->coaId = $coaId;
        $this->coaAsetSaldoAwal = Coa::where('id', $coaId)
            ->where('coa_category', 'aset_tetap')
            ->first();
    }

    public function collection(Collection $rows)
    {
        if (!$this->coaAsetSaldoAwal) {
            $this->errors[] = 'Akun aset tetap saldo awal tidak valid atau bukan kategori Aset Tetap.';
            return;
        }

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
            $coaAkumulasi = Coa::where('coa_code', trim($row[4]))->first();
            $coaPenyusutan = Coa::where('coa_code', trim($row[5]))->first();
            $persentase = $this->normalizeNumericValue($row[6] ?? null);
            $masaManfaat = $this->normalizeNumericValue($row[7] ?? null);
            $note = $row[8] ?? '';

            if (!empty($masaManfaat)) {
                $pilihanNilai = 'masa';
                $masaManfaatValue = $masaManfaat;
                $nilaiPenyusutan = 0;
            } else {
                $pilihanNilai = 'persen';
                $masaManfaatValue = 0;
                $nilaiPenyusutan = $persentase;
            }

            try {
                $storedId = $this->storeSaldoAwalAsetTetap(
                    $asetDate,
                    $namaAset,
                    $nilaiBeli,
                    $this->coaAsetSaldoAwal->id,
                    $coaAkumulasi->id,
                    $coaPenyusutan->id,
                    $nilaiPenyusutan,
                    $masaManfaatValue,
                    $pilihanNilai,
                    $note
                );
                $aset = $storedId ? $this->findImportedAsetById($storedId) : null;

                Log::info('[SaldoAwalAsetTetapImport] baris=' . ($index + 1) . ' id=' . ($storedId ?? 'null') . ' found=' . ($aset ? 'true' : 'false') . ' nama=' . $namaAset);

                if ($storedId && $aset) {
                    $this->successCount++;
                    $this->success[] = 'Baris ' . ($index + 1) . ': Berhasil disimpan.';
                } else {
                    $reason = $storedId
                        ? "Aset tetap ID {$storedId} tidak ditemukan setelah store sukses."
                        : 'Insert aset tetap tidak mengembalikan ID.';

                    $this->recordFailedRow($index, $namaAset, $asetDate, $nilaiBeli, $reason);
                }
            } catch (\Exception $e) {
                Log::warning('[SaldoAwalAsetTetapImport] exception baris ' . ($index + 1) . ': ' . $e->getMessage());
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

        $coaAset = Coa::where('coa_code', trim($row[3]))->first();
        if (!$coaAset) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Aset tidak ditemukan.';
            return true;
        }

        if ((string) $coaAset->id !== (string) $this->coaAsetSaldoAwal->id) {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Aset harus sama dengan akun saldo awal yang sedang dibuka (' . $this->coaAsetSaldoAwal->coa_code . ' - ' . $this->coaAsetSaldoAwal->coa_name . ').';
            return true;
        }

        if ($coaAset->coa_category !== 'aset_tetap') {
            $this->errors[] = 'Baris ' . ($index + 1) . ': Kode Akun Aset bukan kategori Aset Tetap.';
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

    private function storeSaldoAwalAsetTetap(
        $asetDate,
        $namaAset,
        $nilaiBeli,
        $asetTetapCoaId,
        $akumulasiPenyusutanCoaId,
        $penyusutanCoaId,
        $nilaiPenyusutan,
        $masaManfaat,
        $pilihanNilai,
        $note
    ) {
        DB::beginTransaction();

        try {
            $id = DB::table((new PurchaseOrder())->getTable())->insertGetId([
                'no_aset' => OrderRepo::generateCodeTransaction(new PurchaseOrder(), KeyNomor::NO_ORDER_PEMBELIAN_ASET_TETAP, 'no_aset', 'aset_tetap_date'),
                'nama_aset' => $namaAset,
                'aset_tetap_date' => $asetDate,
                'harga_beli' => $nilaiBeli,
                'aset_tetap_coa_id' => $asetTetapCoaId,
                'dari_akun_coa_id' => 0,
                'note' => $note,
                'status_penyusutan' => 1,
                'nilai_penyusutan' => $nilaiPenyusutan,
                'akumulasi_penyusutan_coa_id' => $akumulasiPenyusutanCoaId,
                'penyusutan_coa_id' => $penyusutanCoaId,
                'metode_penyusutan' => '',
                'tanggal_mulai_penyusutan' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'status_aset_tetap' => StatusEnum::OPEN,
                'masa_manfaat' => $masaManfaat,
                'nilai_residu' => 0,
                'pilihan_nilai' => $pilihanNilai,
                'dpp' => 0,
                'ppn' => 0,
                'pilihan' => '',
                'qty' => 1,
                'reason' => '',
                'nilai_akum_penyusutan' => 0,
                'tanggal_input_aset' => $asetDate,
                'akun_selisih' => 0,
                'is_saldo_awal' => 1,
            ]);

            DB::commit();

            return $id;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function findImportedAsetById($id)
    {
        return DB::table((new PurchaseOrder())->getTable())
            ->useWritePdo()
            ->where('id', $id)
            ->first();
    }

    private function recordFailedRow($index, $namaAset, $asetDate, $nilaiBeli, $reason = null)
    {
        $message = 'Baris ' . ($index + 1) . ": Gagal disimpan. Aset: {$namaAset}, tanggal: {$asetDate}, nilai: {$nilaiBeli}.";

        if (!empty($reason)) {
            $message .= " Penyebab: {$reason}.";
        }

        $this->errors[] = $message;

        Log::warning('[SaldoAwalAsetTetapImport] ' . $message);
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
