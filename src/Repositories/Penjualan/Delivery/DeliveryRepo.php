<?php

namespace Icso\Accounting\Repositories\Penjualan\Delivery;

use Exception;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDeliveryMeta;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDeliveryProduct;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDeliveryProductItem;
use Icso\Accounting\Models\Penjualan\Retur\SalesReturProduct;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Penjualan\Order\SalesOrderRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Services\IdentityStockService;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryRepo extends ElequentRepository
{
    protected $model;

    public function __construct(SalesDelivery $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    // ... [Keep getAllDataBy, getAllTotalDataBy as original] ...
    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'], $item['value']['value']);
                    } else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('delivery_no', 'like', '%' .$search. '%')
                ->orWhereHas('vendor', function ($query) use($search) {
                    $query->where('vendor_name', 'like', '%' .$search. '%')
                        ->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                })
                ->orWhereHas('order', function ($query) use($search) {
                    $query->where('order_no', 'like', '%' .$search. '%');
                });
        })->orderBy('delivery_date','desc')
            ->with(['vendor','order','warehouse','deliveryproduct.product','deliveryproduct.items','deliveryproduct.unit','deliveryproduct.tax'])
            ->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'], $item['value']['value']);
                    } else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('delivery_no', 'like', '%' .$search. '%')
                ->orWhereHas('vendor', function ($query) use($search) {
                    $query->where('vendor_name', 'like', '%' .$search. '%')
                        ->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                })
                ->orWhereHas('order', function ($query) use($search) {
                    $query->where('order_no', 'like', '%' .$search. '%');
                });
        })->orderBy('delivery_date','desc')->count();
    }

    /**
     * Store method with Strict Transaction
     */
    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;

        // Prepare Data
        $data = $this->gatherHeaderData($request);

        DB::beginTransaction();
        try {
            // 1. Create/Update Header
            if (empty($id)) {
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['created_by'] = $userId;
                $data['delivery_status'] = StatusEnum::OPEN;
                $res = $this->create($data);
                $deliveryId = $res->id;
            } else {
                $this->update($data, $id);
                $deliveryId = $id;
                $this->deleteAdditional($deliveryId);
            }

            // 2. Process Products
            $products = is_array($request->deliveryproduct)
                ? $request->deliveryproduct
                : json_decode(json_encode($request->deliveryproduct), true);
            $identityService = new IdentityStockService();

            if (!empty($products)) {
                foreach ($products as $itemRaw) {

                    $orderProductId = $itemRaw['order_product_id'];
                    $findProductOrder = SalesOrderProduct::with('product')->find($orderProductId);

                    if (!$findProductOrder) {
                        continue;
                    }

                    $product     = $findProductOrder->product;
                    $sellPrice   = $findProductOrder->price;
                    $isIdentity  = $product && $product->usesIdentityTracking();

                    // Ambil identity items (support 2 payload)
                    $identityItems = $itemRaw['items']
                        ?? $itemRaw['orderproduct']['items']
                        ?? [];

                    /** ===== VALIDASI ===== */
                    if ($isIdentity && empty($identityItems)) {
                        throw new Exception(
                            "Produk {$product->item_name} wajib memilih {$product->identity_label}"
                        );
                    }

                    $qty = 0;
                    $usedIdentity = [];

                    if ($isIdentity) {
                        foreach ($identityItems as $row) {

                            $identityValue = trim($row['identity_value'] ?? '');
                            $rowQty = (float) ($row['qty'] ?? 0);

                            if ($identityValue === '') {
                                throw new Exception("{$product->identity_label} tidak boleh kosong");
                            }

                            if ($rowQty <= 0) {
                                throw new Exception("Qty {$product->identity_label} harus > 0");
                            }

                            if (in_array($identityValue, $usedIdentity)) {
                                throw new Exception("{$product->identity_label} duplikat: {$identityValue}");
                            }

                            $usedIdentity[] = $identityValue;
                            $qty += $rowQty;
                        }
                    } else {
                        $qty = (float) ($itemRaw['qty'] ?? 0);
                    }

                    if ($qty <= 0) {
                        throw new Exception("Qty {$product->item_name} tidak valid");
                    }

                    $subtotal = Helpers::hitungSubtotal(
                        $qty,
                        $sellPrice,
                        $findProductOrder->discount,
                        $findProductOrder->discount_type
                    );

                    $deliveryProduct = SalesDeliveryProduct::create([
                        'delivery_id'       => $deliveryId,
                        'qty'               => $qty,
                        'qty_left'          => $qty,
                        'product_id'        => $itemRaw['product_id'],
                        'unit_id'           => $itemRaw['unit_id'],
                        'order_product_id'  => $orderProductId,
                        'multi_unit'        => 0,
                        'hpp_price'         => 0,
                        'sell_price'        => $sellPrice,
                        'tax_id'            => $findProductOrder->tax_id,
                        'tax_percentage'    => $findProductOrder->tax_percentage,
                        'discount'          => $findProductOrder->discount,
                        'subtotal'          => $subtotal,
                        'tax_type'          => $findProductOrder->tax_type,
                        'discount_type'     => $findProductOrder->discount_type,
                    ]);

                    if ($isIdentity) {
                        foreach ($identityItems as $row) {

                            SalesDeliveryProductItem::create([
                                'delivery_product_id' => $deliveryProduct->id,
                                'product_id'        => $itemRaw['product_id'],
                                'identity_item_id'    => (int) $row['identity_item_id'],
                                'identity_value'      => $row['identity_value'],
                                'qty'                 => (float) $row['qty']
                            ]);
                        }
                        $identityService->consume(
                            collect($identityItems)->map(fn ($row) => [
                                'identity_item_id' => (int) $row['identity_item_id'],
                                'qty'              => (float) $row['qty'],
                            ])->toArray()
                        );
                    }
                }
            }

            // 3. Posting Jurnal (CRITICAL)
            // This calculates HPP, logs inventory, and creates Journals.
            // Throws Exception if unbalanced.
            $this->postingJurnal($deliveryId);

            // 4. Update Order Status
            SalesOrderRepo::changeStatusByDelivery($request->order_id);

            // 5. File Upload
            $this->handleFileUploads($request->file('files'), $deliveryId, $userId);

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Delivery Store Error: " . $e->getMessage());
            return false;
        }
    }

    private function gatherHeaderData(Request $request)
    {
        $deliveryNo = $request->delivery_no ?: self::generateCodeTransaction(new SalesDelivery(), KeyNomor::NO_DELIVERY_ORDER, 'delivery_no', 'delivery_date');

        return [
            'delivery_no'   => $deliveryNo,
            'delivery_date' => !empty($request->delivery_date) ? Utility::changeDateFormat($request->delivery_date) : date('Y-m-d'),
            'order_id'      => $request->order_id,
            'vendor_id'     => $request->vendor_id,
            'warehouse_id'  => $request->warehouse_id,
            'note'          => $request->note,
            'updated_at'    => date('Y-m-d H:i:s'),
            'updated_by'    => $request->user_id,
        ];
    }

    public function deleteAdditional($id)
    {
        InventoryRepo::deleteInventory(TransactionsCode::DELIVERY_ORDER, $id);
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::DELIVERY_ORDER, $id);
        SalesDeliveryProduct::where('delivery_id', $id)->delete();
        SalesDeliveryMeta::where('delivery_id', $id)->delete();
    }

    /**
     * Refactored Posting Jurnal with Balance Check
     */
    public function postingJurnal($id)
    {
        // 1. Eager Load
        $find = $this->model->with(['deliveryproduct.product'])->find($id);

        if (!$find) return;

        // 2. Settings
        $coaSediaanDalamPerjalanan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN_DALAM_PERJALANAN);
        $coaSediaanDefault         = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);

        $inventoryRepo = new InventoryRepo(new Inventory());
        $journalEntries = [];
        $totalHppAll = 0;

        $note = !empty($find->note) ? $find->note : 'Pengiriman Barang';

        // 3. Process Products: Inventory Log + HPP Calculation
        foreach ($find->deliveryproduct as $item) {
            // Get Product COA
            $coaSediaan = $item->product->coa_id ?? $coaSediaanDefault;
            $productName = $item->product->item_name ?? '';

            // Calculate HPP (Moving Average)
            // Note: This must handle the logic: Cost of goods LEAVING warehouse
            $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $find->delivery_date);
            $subtotalHpp = $hpp * $item->qty;
            $totalHppAll += $subtotalHpp;

            // A. Log Inventory Transaction (Outgoing)
            $reqInventory = new Request();
            $reqInventory->coa_id = $coaSediaan;
            $reqInventory->user_id = $find->created_by;
            $reqInventory->inventory_date = $find->delivery_date;
            $reqInventory->transaction_code = TransactionsCode::DELIVERY_ORDER;
            $reqInventory->transaction_id = $find->id;
            $reqInventory->transaction_sub_id = $item->id;
            $reqInventory->qty_out = $item->qty;
            $reqInventory->warehouse_id = $find->warehouse_id;
            $reqInventory->product_id = $item->product_id;
            $reqInventory->price = $hpp; // Store the HPP used
            $reqInventory->note = $find->note;
            $reqInventory->unit_id = $item->unit_id;

            $inventoryRepo->store($reqInventory);

            // B. Prepare Credit Entry (Reduce Inventory Asset)
            // We credit the specific Product Inventory Account
            $journalEntries[] = [
                'coa_id' => $coaSediaan,
                'posisi' => 'kredit',
                'nominal'=> $subtotalHpp,
                'sub_id' => $item->id,
                'note'   => $note . ' (' . $productName . ')'
            ];
        }

        // 4. Prepare Debit Entry (Inventory In Transit / COGS)
        // Usually Delivery Note moves stock to "In Transit" or directly to COGS depending on Incoterms.
        // Assuming Logic: Debit Sediaan Dalam Perjalanan
        if ($totalHppAll > 0) {
            $journalEntries[] = [
                'coa_id' => $coaSediaanDalamPerjalanan,
                'posisi' => 'debet',
                'nominal'=> $totalHppAll,
                'sub_id' => 0,
                'note'   => $note
            ];
        }

        // 5. Validate & Save Journal
        $this->validateAndSaveJournal($journalEntries, $find);
    }

    private function validateAndSaveJournal(array $entries, $deliveryModel)
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $e) {
            if ($e['posisi'] == 'debet') $totalDebit += $e['nominal'];
            else $totalCredit += $e['nominal'];
        }

        // Tolerance 1 Rupiah
        if (abs($totalDebit - $totalCredit) > 1) {
            throw new Exception("Jurnal Delivery {$deliveryModel->delivery_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());

        foreach ($entries as $e) {
            if ($e['nominal'] == 0) continue;

            $jurnalRepo->create([
                'transaction_date'      => $deliveryModel->delivery_date,
                'transaction_datetime'  => $deliveryModel->delivery_date . " " . date('H:i:s'),
                'created_by'            => $deliveryModel->created_by,
                'updated_by'            => $deliveryModel->created_by,
                'transaction_code'      => TransactionsCode::DELIVERY_ORDER,
                'coa_id'                => $e['coa_id'],
                'transaction_id'        => $deliveryModel->id,
                'transaction_sub_id'    => $e['sub_id'],
                'transaction_no'        => $deliveryModel->delivery_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'debet'                 => ($e['posisi'] == 'debet') ? $e['nominal'] : 0,
                'kredit'                => ($e['posisi'] == 'kredit') ? $e['nominal'] : 0,
                'note'                  => $e['note'],
                'created_at'            => date("Y-m-d H:i:s"),
                'updated_at'            => date("Y-m-d H:i:s"),
            ]);
        }
    }

    private function handleFileUploads($uploadedFiles, $deliveryId, $userId)
    {
        if (!empty($uploadedFiles)) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    SalesDeliveryMeta::create([
                        'delivery_id' => $deliveryId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }

    // ... [Keep getDeliveredProduct, getValueSediaanDalamPerjalan, getTotalDelivery, getQtyRetur, changeStatusDelivery as original] ...

    public function getDeliveredProduct($deliveryId,$idProduct,$idUnit)
    {
        return SalesDeliveryProduct::where(['product_id' => $idProduct, 'unit_id' => $idUnit, 'delivery_id' => $deliveryId])->sum('qty');
    }

    public static function getValueSediaanDalamPerjalan($idDelivery, $coaSediaanDalamPerjalananId)
    {
        $find = JurnalTransaksi::where(['transaction_code' => TransactionsCode::DELIVERY_ORDER, 'coa_id' => $coaSediaanDalamPerjalananId, 'transaction_id' => $idDelivery])->first();
        if($find){
            return $find->debet;
        } else {
            // Fallback to Inventory calculation if journal missing (should generally not happen with new atomic logic)
            return Inventory::where(['transaction_code' => TransactionsCode::DELIVERY_ORDER, 'transaction_id' => $idDelivery])->sum('total_out');
        }
    }

    public function getTotalDelivery($id)
    {
        $find = $this->findOne($id,array(),['deliveryproduct']);
        $total = 0;
        if($find && $find->deliveryproduct){
            foreach ($find->deliveryproduct as $item){
                $price = $item->sell_price;
                $subtotal = $item->qty * $price;
                $discount = 0;
                if(!empty($item->discount)){
                    if($item->discount_type == TypeEnum::DISCOUNT_TYPE_PERCENT){
                        $discount = ($item->discount / 100) * $subtotal;
                    } else {
                        $discount = $item->discount;
                    }
                }
                $total += ($subtotal - $discount);
            }
        }
        return $total;
    }

    public function getQtyRetur($delProductId)
    {
        return SalesReturProduct::where('delivery_product_id', $delProductId)->sum('qty');
    }

    public static function changeStatusDelivery($idDelivery, $statusDelivery=StatusEnum::SELESAI)
    {
        $instance = new self(new SalesDelivery());
        $instance->update(['delivery_status' => $statusDelivery], $idDelivery);
    }

    public function resetSalesOrderStatus($orderId)
    {
        if (!empty($orderId)) {
            $count = SalesDelivery::where('order_id', $orderId)->count();
            if ($count == 0) {
                SalesOrderRepo::changeStatusOrderById($orderId, StatusEnum::OPEN);
            } else {
                SalesOrderRepo::changeStatusByDelivery($orderId);
            }
        }
    }
}