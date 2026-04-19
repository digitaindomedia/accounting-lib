<?php
namespace Icso\Accounting\Repositories\Master;

use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\RequestAuditHelper;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseRepo extends ElequentRepository
{
    protected $model;
    protected ActivityLogService $activityLog;

    public function __construct(Warehouse $model, ActivityLogService $activityLog)
    {
        parent::__construct($model);
        $this->model = $model;
        $this->activityLog = $activityLog;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Warehouse();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('warehouse_name', 'like', '%' .$search. '%');
            $query->orWhere('warehouse_code', 'like', '%' .$search. '%');
            $query->orWhere('warehouse_address', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('warehouse_name','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Warehouse();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('warehouse_name', 'like', '%' .$search. '%');
            $query->orWhere('warehouse_code', 'like', '%' .$search. '%');
            $query->orWhere('warehouse_address', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('warehouse_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;
        $whCode = $request->warehouse_code;

        if (empty($whCode)) {
            $whCode = Utility::generateRandomString(5);
        }

        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id)?->toArray();
        }

        $arrData = [
            'warehouse_name' => $request->warehouse_name,
            'warehouse_address' => !empty($request->warehouse_address) ? $request->warehouse_address : '',
            'warehouse_code' => $whCode,
            'warehouse_meta_field' => '',
            'updated_at' => now(),
            'updated_by' => $userId,
        ];

        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_by'] = $userId;
                $arrData['created_at'] = now();

                $result = $this->create($arrData);
                $warehouseId = $result->id;
                $action = 'Tambah data master gudang';
            } else {
                $this->update($arrData, $id);
                $warehouseId = $id;
                $action = 'Edit data master gudang';
            }

            DB::commit();

            $this->activityLog->log([
                'user_id' => $userId,
                'action' => $action,
                'model_type' => Warehouse::class,
                'model_id' => $warehouseId,
                'old_values' => $oldData,
                'new_values' => $this->findOne($warehouseId)?->toArray(),
                'request_payload' => RequestAuditHelper::sanitize($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[WarehouseRepo][store] ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(int $id, int $userId): bool
    {
        $warehouse = Warehouse::find($id);
        if (!$warehouse || !$warehouse->canDelete()) {
            return false;
        }

        $oldData = $warehouse->toArray();

        DB::beginTransaction();
        try {
            $warehouse->delete();
            DB::commit();

            $this->activityLog->log([
                'user_id' => $userId,
                'action' => 'Hapus data master gudang dengan nama ' . $oldData['warehouse_name'],
                'model_type' => Warehouse::class,
                'model_id' => $id,
                'old_values' => $oldData,
                'new_values' => null,
                'request_payload' => RequestAuditHelper::sanitize(request()),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[WarehouseRepo][destroy] ' . $e->getMessage());
            return false;
        }
    }

    public static function getWarehouseId($warehouseCode)
    {
        $warehouse = Warehouse::where('warehouse_code', $warehouseCode)->first();
        return $warehouse ? $warehouse->id : 0;
    }
}
