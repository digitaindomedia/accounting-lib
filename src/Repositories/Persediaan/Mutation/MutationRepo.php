<?php

namespace Icso\Accounting\Repositories\Persediaan\Mutation;


use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\Mutation;
use Icso\Accounting\Models\Persediaan\MutationMeta;
use Icso\Accounting\Models\Persediaan\MutationProduct;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MutationRepo extends ElequentRepository
{
    protected $model;

    public function __construct(Mutation $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
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
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
        })->orderBy('mutation_date','desc')->with(['fromwarehouse','towarehouse','mutationproduct','mutationproduct.product','mutationproduct.unit'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
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
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
        })->orderBy('mutation_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;
        $arrData = $this->prepareMutationData($request, $userId);
        DB::beginTransaction();
        try {
            $res = $this->saveMutation($arrData, $id, $userId);

            if ($res) {
                $idAdjustment = $this->handleProductsAndFiles($request, $res, $id);
                DB::commit();
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function handleProductsAndFiles($request, $res, $id)
    {
        $idMutation = !empty($id) ? $id : $res->id;

        if (!empty($id)) {
            $this->deleteAdditional($id);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->processMutationProducts($request, $idMutation);
        $this->handleFileUploads($request, $idMutation);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        return $idMutation;
    }

    public function prepareMutationData($request, $userId)
    {
        $refNo = $this->getReferenceNumber($request);
        $mutationDate = $this->getMutationDate($request);

        return [
            'ref_no' => $refNo,
            'mutation_date' => $mutationDate,
            'note' => $request->note ?? '',
            'mutation_type' => $request->mutation_type ?? VarType::MUTATION_TYPE_OUT,
            'from_warehouse_id' => $request->from_warehouse_id,
            'to_warehouse_id' => $request->warehouse_id ?? '0',
            'mutation_out_id' => $request->mutation_out_id ?? '0',
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function getReferenceNumber($request)
    {
        return empty($request->ref_no)
            ? self::generateCodeTransaction(new Mutation(), KeyNomor::NO_MUTATION, 'ref_no', 'mutation_date')
            : $request->ref_no;
    }

    private function getMutationDate($request)
    {
        return !empty($request->mutation_date)
            ? Utility::changeDateFormat($request->mutation_date)
            : date('Y-m-d');
    }

    public function saveMutation($arrData, $id, $userId)
    {
        if (empty($id)) {
            $arrData['status_mutation'] = StatusEnum::OPEN;
            $arrData['reason'] = "";
            $arrData['created_at'] = date('Y-m-d H:i:s');
            $arrData['created_by'] = $userId;
            return $this->create($arrData);
        } else {
            return $this->update($arrData, $id);
        }
    }

    public function processMutationProducts($request, $idMutation)
    {

        if (!empty($request->mutationproduct)) {
            if (is_array($request->mutationproduct)) {
                $products = json_decode(json_encode($request->mutationproduct));
            } else {
                $products = $request->mutationproduct;
            }
            $this->mutationProducts($products, $idMutation);
        }
    }

    public function mutationProducts($products, $mutationId)
    {
        if (count($products) > 0) {
            foreach ($products as $item) {
                $arrItem = [
                    'qty' => $item->qty,
                    'price' => Utility::remove_commas($item->price),
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'mutation_id' => $mutationId,
                ];
                MutationProduct::create($arrItem);
            }
        }
    }

    public function handleFileUploads($request, $idAdjustment)
    {
        $fileUpload = new FileUploadService();
        $uploadedFiles = $request->file('files');
        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                if ($resUpload) {
                    MutationMeta::create([
                        'mutation_id' => $idAdjustment,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload,
                    ]);
                }
            }
        }
    }

    public function deleteAdditional($id)
    {
        MutationProduct::where(array('mutation_id' => $id))->delete();
        MutationMeta::where(array('mutation_id' => $id))->delete();
        Inventory::where(array('transaction_code' => TransactionsCode::MUTATION, 'transaction_id' => $id))->delete();

    }
}
