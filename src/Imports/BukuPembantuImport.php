<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Repositories\Akuntansi\BukuPembantuRepo;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class BukuPembantuImport implements ToCollection
{
    protected $userId;
    protected $coaId;
    protected $bukuPembantuRepo;
    private $errors = [];
    private $success = [];
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId, $coaId, BukuPembantuRepo $bukuPembantuRepo)
    {
        $this->userId = $userId;
        $this->coaId = $coaId;
        $this->bukuPembantuRepo = $bukuPembantuRepo;
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

            $refDate = $row[0];
            $refNo = $row[1];
            $note = $row[2];
            $fieldName = $row[3];
            $nominal = Utility::remove_commas($row[4]);

            try {
                $req = new Request();
                $req->coa_id = $this->coaId;
                $req->user_id = $this->userId;
                $req->ref_date = date("Y-m-d", strtotime($refDate));
                $req->ref_no = $refNo;
                $req->note = $note;
                $req->field_name = $fieldName;
                $req->nominal = $nominal;
                $req->input_type = InputType::SALDO_AWAL;

                $this->bukuPembantuRepo->store($req);

                $this->successCount++;
                $this->success[] = "Baris " . ($index + 1) . ": Berhasil disimpan.";

            } catch (\Exception $e) {
                $this->errors[] = "Baris " . ($index + 1) . ": Error: " . $e->getMessage();
            }
        }
    }

    private function hasValidationErrors($index, $row)
    {
        if (empty($row[0])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Tanggal Kosong.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": No Ref Kosong.";
            return true;
        }
        if (empty($row[3])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Nama Field Kosong.";
            return true;
        }
        if (empty($row[4])) {
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
