<?php

namespace Icso\Accounting\Imports;

use Icso\Accounting\Models\Akuntansi\SaldoAwal;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class StockAwalImport implements ToCollection
{
    protected $userId;
    protected $coaId;
    protected $inventoryRepo;
    private $errors = [];
    private $success = [];
    private $totalRows = 0;
    private $successCount = 0;

    public function __construct($userId, $coaId, InventoryRepo $inventoryRepo)
    {
        $this->userId = $userId;
        $this->coaId = $coaId;
        $this->inventoryRepo = $inventoryRepo;
    }

    public function collection(Collection $rows)
    {
        $findSaldoAwalData = SaldoAwal::where(array('is_default' => '1'))->first();
        $saldoAwalDate = date('Y-m-d H:i:s');
        if(!empty($findSaldoAwalData)){
            $saldoAwalDate = $findSaldoAwalData->saldo_date." ".date('H:i:s');
        }

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }
            $this->totalRows++;

            if ($this->hasValidationErrors($index, $row)) {
                continue;
            }

            $itemCode = $row[0];
            $warehouseCode = $row[1];
            $qty = $row[2];
            $price = Utility::remove_commas($row[3]);
            $note = $row[4];

            $product = Product::where('item_code', $itemCode)->first();
            $warehouse = Warehouse::where('warehouse_code', $warehouseCode)->first();

            $total = $qty * $price;

            try {
                $req = new Request();
                $req->coa_id = $this->coaId;
                $req->user_id = $this->userId;
                $req->inventory_date = $saldoAwalDate;
                $req->transaction_code = TransactionsCode::SALDO_AWAL;
                $req->qty_in = $qty;
                $req->warehouse_id = $warehouse->id;
                $req->product_id = $product->id;
                $req->price = $price;
                $req->note = $note;
                $req->unit_id = $product->unit_id;

                $arrStockAwal = array(
                    'stock_date' => $saldoAwalDate,
                    'product_id' => $product->id,
                    'qty' => $qty,
                    'unit_id' => $product->unit_id,
                    'warehouse_id' => $warehouse->id,
                    'total' => $total,
                    'coa_id' => $this->coaId,
                    'nominal' => $price,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $this->userId,
                    'created_by' => $this->userId
                );
                $resStock = StockAwal::create($arrStockAwal);
                $req->transaction_id = $resStock->id;
                $this->inventoryRepo->store($req);

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
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Barang Kosong.";
            return true;
        }
        if (!Product::where('item_code', $row[0])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Barang tidak ditemukan.";
            return true;
        }
        if (empty($row[1])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Gudang Kosong.";
            return true;
        }
        if (!Warehouse::where('warehouse_code', $row[1])->exists()) {
            $this->errors[] = "Baris " . ($index + 1) . ": Kode Gudang tidak ditemukan.";
            return true;
        }
        if (empty($row[2])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Qty Kosong.";
            return true;
        }
        if (empty($row[3])) {
            $this->errors[] = "Baris " . ($index + 1) . ": Harga Satuan Kosong.";
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
