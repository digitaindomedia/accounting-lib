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
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class JurnalKasBankImport implements ToCollection
{
    protected $userId;
    protected $jurnalType;
    private $errors = [];
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId, $jurnalType)
    {
        $this->userId = $userId;
        $this->jurnalType = $jurnalType;
    }

    public function collection(Collection $rows)
    {
        $oldNo = null;
        $idJurnal = null;
        $totalBal = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Skip header
                if ($index === 0) continue;

                $this->totalRows++;

                $noJurnal = trim($row[0]);
                $jurnalDate = $row[1];
                $coaCodeKasBank = trim($row[2]);
                $transType = trim(strtolower($row[3]));
                $person = $row[4];
                $note = $row[5];
                $coaCodeItem = trim($row[6]);
                $nominal = $row[7];
                $noteItem = $row[8];

                $coaKasBank = null;
                $coaItem = null;

                if ($this->hasValidationErrors($index, $row, $coaKasBank, $coaItem)) {
                    continue;
                }

                if (!empty($jurnalDate)) {
                    $jurnalDate = Helpers::formatDateExcel($jurnalDate);
                }

                $nominalValue = Utility::remove_commas($nominal);

                if (is_null($oldNo)) {
                    // Pertama kali insert
                    if (Jurnal::where('jurnal_no', $noJurnal)->exists()) {
                        $this->errors[] = "Baris " . ($index + 1) . ": No Jurnal '$noJurnal' sudah digunakan.";
                        continue;
                    }

                    $idJurnal = $this->insertJurnalEntry($noJurnal, $jurnalDate, $note, $coaKasBank, $transType, $person, $coaItem, $nominalValue, $noteItem, $totalBal);
                    if ($idJurnal) $this->successCount++;
                    $oldNo = $noJurnal;

                } elseif ($oldNo !== $noJurnal) {
                    // Ganti Jurnal baru
                   // $this->updateNominalKasBank($idJurnal, $totalBal);
                    $totalBal = 0;

                    if (Jurnal::where('jurnal_no', $noJurnal)->exists()) {
                        $this->errors[] = "Baris " . ($index + 1) . ": No Jurnal '$noJurnal' sudah digunakan.";
                        continue;
                    }

                    $idJurnal = $this->insertJurnalEntry($noJurnal, $jurnalDate, $note, $coaKasBank, $transType, $person, $coaItem, $nominalValue, $noteItem, $totalBal);
                    if ($idJurnal) $this->successCount++;
                    $oldNo = $noJurnal;

                } else {
                    // Masih satu jurnal
                    $transTypeEnum = $transType == 'masuk' ? JurnalType::INCOME_TYPE : JurnalType::EXPENSE_TYPE;
                    $this->createJurnalAkunAndTransaksi($idJurnal, $noJurnal, $jurnalDate, $noteItem, $coaItem, $transTypeEnum, $nominalValue, $totalBal);
                    $this->updateNominalKasBank($idJurnal, $totalBal);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errors[] = "Error saat import: " . $e->getMessage();
        }
    }

    private function hasValidationErrors($index, $row, &$coaKasBank = null, &$coaItem = null)
    {
        $rowNumber = $index + 1;

        if (empty($row[0])) {
            $this->errors[] = "Baris $rowNumber: No bukti kas/bank/giro kosong.";
            return true;
        }

        if (empty($row[1])) {
            $this->errors[] = "Baris $rowNumber: Tanggal bukti kosong.";
            return true;
        }

        if (empty($row[2])) {
            $this->errors[] = "Baris $rowNumber: Kode COA Kas/Bank/Giro kosong.";
            return true;
        } else {
            $coaKasBank = Coa::where('coa_code', trim($row[2]))->first();
            if (!$coaKasBank) {
                $this->errors[] = "Baris $rowNumber: Kode COA Kas/Bank tidak ditemukan.";
                return true;
            }
        }

        if (empty($row[3]) || !in_array(strtolower($row[3]), ['masuk', 'keluar'])) {
            $this->errors[] = "Baris $rowNumber: Jenis transaksi tidak valid.";
            return true;
        }

        if (empty($row[6])) {
            //$this->errors[] = "Baris $rowNumber: Kode COA item kosong.";
            //return true;
        } else {
            $coaItem = Coa::where('coa_code', trim($row[6]))->first();
            if (!$coaItem) {
                $this->errors[] = "Baris $rowNumber: Kode COA item tidak ditemukan.";
                return true;
            }
        }

        if (empty($row[7])) {
           // $this->errors[] = "Baris $rowNumber: Nominal kosong.";
            //return true;
        }

        return false;
    }

    private function insertJurnalEntry($noJurnal, $jurnalDate, $note, $coaKasBank, $transType, $person, $coaItem, $nominal, $noteItem, &$totalBal)
    {
        $transTypeEnum = $transType == 'masuk' ? JurnalType::INCOME_TYPE : JurnalType::EXPENSE_TYPE;

        $jurnal = Jurnal::create([
            'jurnal_date' => $jurnalDate,
            'jurnal_no' => $noJurnal,
            'updated_at' => now(),
            'updated_by' => $this->userId,
            'note' => $note ?? '',
            'jurnal_type' => $this->jurnalType,
            'status_jurnal' => JurnalStatusEnum::OK,
            'coa_id' => $coaKasBank->id,
            'transaction_type' => $transTypeEnum,
            'person' => $person,
            'nominal' => 0,
        ]);

        if ($jurnal) {
            $this->createJurnalAkunAndTransaksi($jurnal->id, $noJurnal, $jurnalDate, $noteItem, $coaItem, $transTypeEnum, $nominal, $totalBal);
        }

        return $jurnal->id;
    }

    private function createJurnalAkunAndTransaksi($jurnalId, $noJurnal, $jurnalDate, $note, $coa, $transTypeEnum, $nominal, &$totalBal)
    {
        if (!$coa) {
            return;
        }
        $jurnalDateTime = "$jurnalDate " . now()->format('H:i:s');

        $jurnalAkun = JurnalAkun::create([
            'jurnal_id' => $jurnalId,
            'coa_id' => $coa->id,
            'data_sess' => '',
            'debet' => 0,
            'kredit' => 0,
            'nominal' => $nominal,
            'note' => $note,
        ]);

        $debet = $transTypeEnum == JurnalType::EXPENSE_TYPE ? $nominal : 0;
        $kredit = $transTypeEnum == JurnalType::INCOME_TYPE ? $nominal : 0;

        JurnalTransaksi::create([
            'transaction_date' => $jurnalDate,
            'transaction_datetime' => $jurnalDateTime,
            'created_by' => $this->userId,
            'updated_by' => $this->userId,
            'transaction_code' => TransactionsCode::JURNAL,
            'coa_id' => $coa->id,
            'transaction_id' => $jurnalId,
            'transaction_sub_id' => $jurnalAkun->id,
            'created_at' => now(),
            'updated_at' => now(),
            'transaction_no' => $noJurnal,
            'transaction_status' => JurnalStatusEnum::OK,
            'debet' => $debet,
            'kredit' => $kredit,
            'note' => $note,
        ]);

        $totalBal += $nominal;
    }

    public function updateNominalKasBank($idJurnal, $totalBal)
    {
        $jurnal = Jurnal::find($idJurnal);

        if ($jurnal) {
            $jurnal->nominal = $totalBal;
            $jurnal->save();

            $jurnalDateTime = $jurnal->jurnal_date . " " . now()->format('H:i:s');

            $debet = $jurnal->transaction_type == JurnalType::INCOME_TYPE ? $totalBal : 0;
            $kredit = $jurnal->transaction_type == JurnalType::EXPENSE_TYPE ? $totalBal : 0;

            JurnalTransaksi::create([
                'transaction_date' => $jurnal->jurnal_date,
                'transaction_datetime' => $jurnalDateTime,
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
                'transaction_code' => TransactionsCode::JURNAL,
                'coa_id' => $jurnal->coa_id,
                'transaction_id' => $jurnal->id,
                'transaction_sub_id' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                'transaction_no' => $jurnal->jurnal_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $debet,
                'kredit' => $kredit,
                'note' => $jurnal->note,
            ]);
        }
    }

    public function getErrors(): array
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
