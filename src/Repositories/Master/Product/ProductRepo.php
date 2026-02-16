<?php
namespace Icso\Accounting\Repositories\Master\Product;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductCategory;
use Icso\Accounting\Models\Master\ProductMeta;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\Constants;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductRepo extends ElequentRepository
{

    protected $model;

    public function __construct(Product $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new Product();

        $dataSet = $model->when(!empty($search), function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', '%' . $search . '%')
                    ->orWhere('item_code', 'like', '%' . $search . '%')
                    ->orWhere('descriptions', 'like', '%' . $search . '%');
            });
        })->when(!empty($where['product_type']), function ($query) use ($where) {
            $query->where('product_type', $where['product_type']);
        })->when(!empty($where['category_id']), function ($query) use ($where) {
            $query->whereHas('categories', function ($q) use ($where) {
                $q->where('category_id', $where['category_id']);
            });
        })->with(['unit', 'categories', 'productconvertion'])
            ->orderBy('item_name', 'asc')
            ->offset($page)
            ->limit($perpage)
            ->get();

        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Product();
        $dataSet = $model->when(!empty($search), function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', '%' . $search . '%')
                    ->orWhere('item_code', 'like', '%' . $search . '%')
                    ->orWhere('descriptions', 'like', '%' . $search . '%');
            });
        })->when(!empty($where['product_type']), function ($query) use ($where) {
            $query->where('product_type', $where['product_type']);
        })->when(!empty($where['category_id']), function ($query) use ($where) {
            $query->whereHas('categories', function ($q) use ($where) {
                $q->where('category_id', $where['category_id']);
            });
        })->orderBy('item_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        if(!empty($request->selling_price)) {
            $selling_price = Utility::remove_commas($request->selling_price);
        } else
        {
            $selling_price = '0';
        }
        $isHasTax = $request->is_has_tax;
        $itemCode = $request->item_code;
        if(empty($itemCode)) {
            $itemCode = $this->autoGenerateCode($request->item_name);
        }
        $arrData = array(
            'item_name' => $request->item_name,
            'item_code' => $itemCode,
            'descriptions' => (!empty($request->descriptions) ? $request->descriptions : ''),
            'min_stock' => (!empty($request->min_stock) ? $request->min_stock : '0'),
            'unit_id' => $request->unit_id,
            'selling_price' => $selling_price,
            'is_has_tax' => !empty($isHasTax) ? $isHasTax : '0',
            'status_price' => !empty($request->status_price) ? $request->status_price : '0',
            'coa_id' => !empty($request->coa_id) ? $request->coa_id : '0',
            'coa_biaya_id' => !empty($request->coa_biaya_id) ? $request->coa_biaya_id : '0',
            'type_price' => '',
            'is_identity_tracking' => $request->boolean('is_identity_tracking'),
            'identity_label' => $request->identity_label,
            'updated_by' => $request->user_id,
            'updated_at' => date('Y-m-d H:i:s'),
        );
        DB::beginTransaction();
        $res = '';
        try {
            if (empty($id)) {
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $request->user_id;
                $arrData['product_type'] = $request->product_type;
                $arrData['item_status'] = Constants::AKTIF;
                $arrData['item_photo'] = '';
                $res = $this->create($arrData);
            } else {
                $res = $this->update($arrData, $id);
            }
            if ($res) {
                if (!empty($id)) {
                    $productId = $id;
                    $this->deleteAdditional($id);
                } else {
                    $productId = $res->id;
                }
                if (!empty($request->category)) {
                    ProductCategory::create(array(
                        'product_id' => $productId,
                        'category_id' => $request->category,
                    ));
                }
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                $statusUpload = true;
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            try {
                                $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                                if ($resUpload) {
                                    $arrUpload = array(
                                        'product_id' => $productId,
                                        'meta_key' => 'upload',
                                        'meta_value' => $resUpload
                                    );
                                    ProductMeta::create($arrUpload);
                                }
                                $statusUpload = true;
                            } catch (\Exception $e) {
                                Log::error($e->getMessage());
                                $statusUpload = false;
                                break;
                            }

                        }
                    }
                }
                $message = "Data berhasil disimpan";
                if(!$statusUpload) {
                    $message = "Kouta penyimpanan Anda sudah penuh, beberapa gambar gagal disimpan";
                }
                DB::commit();
                return array('status' => true, 'message' => $message);
            } else {
                return array('status' => false, 'message' => 'Data gagal disimpan');
            }
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollback();
            return array('status' => false, 'message' => 'Data gagal disimpan');
        }
    }

    public function deleteAdditional($id)
    {
        ProductCategory::where(array('product_id' => $id))->delete();
        ProductMeta::where(array('product_id' => $id))->delete();
    }

    public function autoGenerateCode($str)
    {
        // TODO: Implement autoGenerateCode() method.
        $first_letter = 'A';
        if(!empty($str))
        {
            $first_letter = substr($str,0,1);
        }
        $nextId = $first_letter.'00001';
        $res = Product::orderBy('id','desc')->first();
        if(!empty($res)) {
            $idPostFix = $res->id + 1;
            $nextId = $first_letter.STR_PAD((string)$idPostFix,5,"0",STR_PAD_LEFT);
        }
        return $nextId;
    }

    public function getAllDataProduct($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Product();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('item_name', 'like', '%' .$search. '%');
            $query->orWhere('item_code', 'like', '%' .$search. '%');
            $query->orWhere('descriptions', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
            $query->whereHas('categories', function ($query) use ($where) {
                $query->where($where);
            });
        })->orderBy('item_name','asc');
        return $dataSet;
    }

    public static function getProductId($productCode)
    {
        $product = Product::where('item_code', $productCode)->first();
        return $product ? $product->id : 0;
    }

    public static function getAllCategoriesById($productId)
    {
        $product = Product::find($productId);
        $categories = $product->categories->pluck('category_name')->implode(', ');
        return $categories;
    }

    public static function getSatuanById($productId)
    {
        $product = Product::find($productId);
        $satuan = $product->satuan;
        return $satuan;
    }
}
