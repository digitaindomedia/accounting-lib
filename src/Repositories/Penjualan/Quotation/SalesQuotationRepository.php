<?php
namespace Icso\Accounting\Repositories\Penjualan\Quotation;


use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderMeta;
use Icso\Accounting\Models\Penjualan\Order\SalesQuotation;
use Icso\Accounting\Models\Penjualan\Order\SalesQuotationMeta;
use Icso\Accounting\Models\Penjualan\Order\SalesQuotationProduct;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\Utility;
use Illuminate\Support\Facades\DB;
use Exception;

class SalesQuotationRepository implements SalesQuotationRepositoryInterface
{
    public function findOne($id, $select_field = [])
    {
        if (is_array($select_field) && count($select_field)) {
            return SalesQuotation::select($select_field)->where('id', $id)->with(['quotationproduct.product', 'quotationproduct.unit','quotationproduct.tax', 'quotationproduct','quotationproduct'])->first();
        }

        return SalesQuotation::where('id', $id)->with(['quotationproduct.product', 'quotationproduct.unit', 'quotationproduct.tax', 'quotationproduct'])->first();
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [], $fromDate = null, $untilDate = null)
    {
        $model = new SalesQuotation();

        $dataSet = $model
            ->when(!empty($where), function ($query) use ($where) {
                $query->where($where);
            })
            ->when(!empty($search), function ($query) use ($search) {
                $query->where('quotation_no', 'LIKE', '%' . $search . '%')
                    ->orWhere('note', 'LIKE', '%' . $search . '%');
            })
            ->when(!empty($fromDate) && !empty($untilDate), function ($query) use ($fromDate, $untilDate) {
                $query->whereBetween('quotation_date', [$fromDate, $untilDate]);
            })
            ->when(!empty($fromDate) && empty($untilDate), function ($query) use ($fromDate) {
                $query->whereDate('quotation_date', '>=', $fromDate);
            })
            ->when(empty($fromDate) && !empty($untilDate), function ($query) use ($untilDate) {
                $query->whereDate('quotation_date', '<=', $untilDate);
            })->with(['quotationproduct.product', 'quotationproduct.unit','quotationproduct.tax','quotationproduct'])
            ->orderBy('quotation_date', 'desc');

        return $dataSet->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBy($search, array $where = [], $fromDate = null, $untilDate = null)
    {
        $model = new SalesQuotation();

        $dataSet = $model
            ->when(!empty($where), function ($query) use ($where) {
                $query->where($where);
            })
            ->when(!empty($search), function ($query) use ($search) {
                $query->where('quotation_no', 'LIKE', '%' . $search . '%')
                    ->orWhere('note', 'LIKE', '%' . $search . '%');
            })
            ->when(!empty($fromDate) && !empty($untilDate), function ($query) use ($fromDate, $untilDate) {
                $query->whereBetween('quotation_date', [$fromDate, $untilDate]);
            })
            ->when(!empty($fromDate) && empty($untilDate), function ($query) use ($fromDate) {
                $query->whereDate('quotation_date', '>=', $fromDate);
            })
            ->when(empty($fromDate) && !empty($untilDate), function ($query) use ($untilDate) {
                $query->whereDate('quotation_date', '<=', $untilDate);
            });

        return $dataSet->count();
    }

    public function create(array $data)
    {
        $res = new SalesQuotation();
        $res->fill($data)->save();

        return $res->id;
    }

    public function update(array $data, $id)
    {
        $query = SalesQuotation::findOrFail($id);

        return $query->update($data);
    }

    public function delete($id)
    {
        return SalesQuotation::where(['id' => $id])->delete();
    }

    public function deleteAll(array $ids)
    {
        return SalesQuotation::whereIn('id', $ids)->delete();
    }

    public function getAll($search, $page, $perpage, array $where = [], $fromDate = null, $untilDate = null)
    {
        return [
            'data' => $this->getAllDataBy($search, $page, $perpage, $where, $fromDate, $untilDate),
            'total' => $this->getAllTotalDataBy($search, $where, $fromDate, $untilDate),
        ];
    }

    public function find($id)
    {
        $quotation = $this->findOne($id);
        if ($quotation) {
            $quotation->load('quotationproduct.product', 'quotationproduct.unit', 'quotationproduct.tax');
        }
        return $quotation;
    }

    public function store(array $data)
    {
        DB::beginTransaction();
        try {
            $id = $data['id'] ?? 0;
            $userId = $data['user_id'] ?? 0;

            $arrData = [
                'quotation_no' => !empty($data['quotation_no']) ? $data['quotation_no'] : ElequentRepository::generateCodeTransaction(new SalesQuotation(), 'NO_QUOTATION_PENJUALAN','quotation_no','quotation_date'),
                'quotation_date' => !empty($data['quotation_date']) ? Utility::changeDateFormat($data['quotation_date']) : date("Y-m-d"),
                'note' => $data['note'] ?? '',
                'quotation_status' => $data['quotation_status'] ?? StatusEnum::OPEN,
                'reason' => $data['reason'] ?? '',
                'tax_type' => $data['tax_type'] ?? null,
                'subtotal' => $data['subtotal'] ?? 0,
                'dpp' => $data['dpp'] ?? 0,
                'total_tax' => $data['total_tax'] ?? 0,
                'grandtotal' => $data['grandtotal'] ?? 0,
                'updated_by' => $data['updated_by'] ?? $userId,
            ];

            if (empty($id)) {
                $arrData['created_by'] = $data['created_by'] ?? $userId;
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['updated_at'] = date('Y-m-d H:i:s');
                $id = $this->create($arrData);
            } else {
                $arrData['updated_at'] = date('Y-m-d H:i:s');
                $this->update($arrData, $id);
            }

            // Handle products
            if (isset($data['quotationproduct']) && is_array($data['quotationproduct'])) {
                // Delete existing products for this quotation
                SalesQuotationProduct::where('quotation_id', $id)->delete();

                foreach ($data['quotationproduct'] as $product) {
                    $taxGroup = null;
                    if (($product['tax_type'] ?? null) === 'multiple' && !empty($product['tax']['taxgroup'])) {
                        $taxGroup = json_encode($product['tax']['taxgroup']);
                    }
                    SalesQuotationProduct::create([
                        'quotation_id' => $id,
                        'product_id' => $product['product_id'],
                        'qty' => $product['qty'],
                        'qty_left' => $product['qty'], // Initialize qty_left with qty
                        'price' => $product['price'] ?? 0,
                        'tax_id' => !empty($product['tax_id']) && $product['tax_id'] !== '0' ? $product['tax_id'] : null,
                        'tax_group' => $taxGroup,
                        'tax_percentage' => $product['tax_percentage'] ?? 0,
                        'tax_type' => $product['tax_type'] ?? null,
                        'unit_id' => $product['unit_id'] ?? null,
                        'subtotal' => $product['subtotal'] ?? 0,
                        'note' => $product['note'] ?? null,
                        'multi_unit' => $product['multi_unit'] ?? 0,
                    ]);
                }
            }
            $this->handleFileUploads($data['files'] ?? [], $id, $userId);
            DB::commit();
            return $id;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function handleFileUploads($uploadedFiles, $quotationId, $userId)
    {
        if (!empty($uploadedFiles)) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    $arrUpload = [
                        'quotation_id' => $quotationId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ];
                    SalesQuotationMeta::create($arrUpload);
                }
            }
        }
    }

    public function deleteData($id)
    {
        DB::beginTransaction();
        try {
            SalesQuotationProduct::where('quotation_id', $id)->delete();
            SalesQuotationMeta::where('quotation_id', $id)->delete();
            $result = $this->delete($id);
            DB::commit();
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteAllData(array $ids)
    {
        DB::beginTransaction();
        try {
            SalesQuotationProduct::whereIn('quotation_id', $ids)->delete();
            SalesQuotationMeta::where('quotation_id', $ids)->delete();
            $result = $this->deleteAll($ids);
            DB::commit();
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
