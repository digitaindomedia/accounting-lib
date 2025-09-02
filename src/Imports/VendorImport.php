<?php

namespace Icso\Accounting\Imports;


use Icso\Accounting\Models\Master\Vendor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class VendorImport implements ToCollection
{
    protected $userId;
    protected $vendorType;
    private $errors = [];
    private $successCount = 0;
    private $rowResults = [];
    private $totalRows = 0;
    public function __construct($userId, $vendorType)
    {
        $this->userId = $userId;
        $this->vendorType = $vendorType;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            // Skip the header row
            if ($index === 0) {
                continue;
            }
            $this->totalRows++;
            $rowNumber = $index + 1;
            $rowData = [
                'row' => $rowNumber,
                'note' => "Kode '{$row[0]}' berhasil import." ?? null,
                'status' => 'success',
                'message' => 'Data berhasil diimport.'
            ];

            $rowHasError = false;
            $messages = [];

            // Check if item code is empty
            if (empty($row[0])) {
                $messages[] = "Kode masih kosong.";
                $rowHasError = true;
            } elseif(Vendor::where('vendor_code', $row[0])->exists()) {
                $messages[] = "Kode '{$row[0]}' sudah ada.";
                $rowHasError = true;
            }

            if (empty($row[1])) {
                $messages[] = "Nama masih kosong.";
                $rowHasError = true;
            }

            if (empty($row[2])) {
                $messages[] = "Nama perusahaan masih kosong.";
                $rowHasError = true;
            }

            if ($rowHasError) {
                $rowData['status'] = 'error';
                $rowData['message'] = implode(' ', $messages);
                $rowData['note'] = implode(' ', $messages);
                $this->errors[] = "Baris {$rowNumber}: {$rowData['message']}";
                $this->rowResults[] = $rowData;
                continue;
            }

            $vendor = Vendor::create([
                'vendor_code' => $row[0],
                'vendor_name' => $row[1],
                'vendor_company_name' => $row[2],
                'vendor_email' => $row[3] ?? "",
                'vendor_phone' => $row[4] ?? "",
                'vendor_address' => $row[5] ?? "",
                'vendor_npwp' => $row[6] ?? "",
                'vendor_type' => $this->vendorType,
                'vendor_ktp' => '',
                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);

            $this->successCount++;
            $this->rowResults[] = $rowData;
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSuccessCount()
    {
        return $this->successCount;
    }

    public function getRowResults()
    {
        return $this->rowResults;
    }

    public function getTotalRows()
    {
        return $this->totalRows;
    }
}
