<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Models\Akuntansi\Jurnal;
use Icso\Accounting\Models\Akuntansi\JurnalAkun;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\JurnalType;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class JurnalUmumImport implements ToCollection
{
    protected $userId;
    private array $errors = [];
    private int $totalRows = 0;
    private int $successCount = 0;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function collection(Collection $rows)
    {
        $previousNo = null;
        $currentJurnalId = null;
        $totalNominal = 0;

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Skip header

            $this->totalRows++;

            [$noJurnal, $jurnalDate, $note, $coaCode, $debet, $kredit] = $row;

            if ($this->hasValidationErrors($index, $row, $debet, $kredit)) continue;

            $jurnalDate = Helpers::formatDateExcel($jurnalDate);
            if (!$jurnalDate) {
                $this->errors[] = "Baris " . ($index + 1) . ": Format tanggal tidak valid.";
                continue;
            }

            $coa = Coa::where('coa_code', $coaCode)->first();
            if (!$coa) {
                $this->errors[] = "Baris " . ($index + 1) . ": Kode COA '$coaCode' tidak ditemukan.";
                continue;
            }

            if ($previousNo === null || $previousNo !== $noJurnal) {
                if ($currentJurnalId !== null) {
                    $this->updateJurnalEntry($currentJurnalId, $totalNominal);
                    $totalNominal = 0;
                }

                $currentJurnalId = $this->insertJurnalEntry($noJurnal, $jurnalDate, $note);
                if ($currentJurnalId) $this->successCount++;

                $previousNo = $noJurnal;
            }

            $this->insertJurnalDetail($currentJurnalId, $coa, $jurnalDate, $note, $noJurnal, $debet, $kredit, $totalNominal);
        }

        // Final jurnal update
        if ($currentJurnalId) {
            $this->updateJurnalEntry($currentJurnalId, $totalNominal);
        }
    }

    private function hasValidationErrors($index, $row, $debet, $kredit): bool
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": No jurnal kosong.";
            return true;
        }

        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal jurnal kosong.";
            return true;
        }

        if (empty($row[3])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode COA kosong.";
            return true;
        }

        if (empty($debet) && empty($kredit)) {
            $this->errors[] = "Baris " . ($index + 1) . ": Debet dan kredit kosong.";
            return true;
        }

        return false;
    }

    private function insertJurnalEntry($noJurnal, $jurnalDate, $note): ?int
    {
        $jurnal = Jurnal::create([
            'jurnal_date' => $jurnalDate,
            'jurnal_no' => $noJurnal,
            'note' => $note ?? '',
            'jurnal_type' => JurnalType::JURNAL_UMUM,
            'status_jurnal' => JurnalStatusEnum::OK,
            'coa_id' => 0,
            'transaction_type' => '',
            'person' => '',
            'nominal' => 0,
            'updated_by' => $this->userId,
        ]);

        return $jurnal?->id;
    }

    private function insertJurnalDetail($jurnalId, Coa $coa, $jurnalDate, $note, $noJurnal, $debet, $kredit, &$totalNominal)
    {
        $debetValue = !empty($debet) ? Utility::remove_commas($debet) : 0;
        $kreditValue = !empty($kredit) ? Utility::remove_commas($kredit) : 0;

        $resItem = JurnalAkun::create([
            'jurnal_id' => $jurnalId,
            'coa_id' => $coa->id,
            'debet' => $debetValue,
            'kredit' => $kreditValue,
            'nominal' => 0,
            'note' => $note,
            'data_sess' => '',
        ]);

        JurnalTransaksi::create([
            'transaction_date' => $jurnalDate,
            'transaction_datetime' => "$jurnalDate " . now()->format('H:i:s'),
            'transaction_code' => TransactionsCode::JURNAL,
            'coa_id' => $coa->id,
            'transaction_id' => $jurnalId,
            'transaction_sub_id' => $resItem->id,
            'transaction_no' => $noJurnal,
            'transaction_status' => JurnalStatusEnum::OK,
            'note' => $note,
            'debet' => $debetValue,
            'kredit' => $kreditValue,
            'created_by' => $this->userId,
            'updated_by' => $this->userId,
        ]);

        $totalNominal += $debetValue;
    }

    private function updateJurnalEntry($jurnalId, $totalNominal)
    {
        if ($jurnalId && $jurnalId !== '0') {
            Jurnal::where('id', $jurnalId)->update(['nominal' => $totalNominal]);
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }
}
