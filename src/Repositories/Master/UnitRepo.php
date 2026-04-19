<?php
namespace Icso\Accounting\Repositories\Master;

use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Utils\RequestAuditHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitRepo extends ElequentRepository
{

    protected $model;
    protected ActivityLogService $activityLog;

    public function __construct(Unit $model, ActivityLogService $activityLog)
    {
        parent::__construct($model);
        $this->model = $model;
        $this->activityLog = $activityLog;
    }


    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Unit();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('unit_name', 'like', '%' .$search. '%');
            $query->orWhere('unit_code', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('unit_name','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Unit();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('unit_name', 'like', '%' .$search. '%');
            $query->orWhere('unit_code', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('unit_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;

        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id)?->toArray();
        }

        $arrData = array(
            'unit_name' => $request->unit_name,
            'unit_code' => $request->unit_code,
            'unit_description' => !empty($request->unit_description) ? $request->unit_description: '',
            'updated_at' => now(),
            'updated_by' => $userId
        );

        DB::beginTransaction();
        try {
            if(empty($id)) {
                $arrData['created_at'] = now();
                $arrData['created_by'] = $userId;
                $result = $this->create($arrData);
                $unitId = $result->id;
                $action = 'Tambah data master satuan';
            } else {
                $this->update($arrData,$id);
                $unitId = $id;
                $action = 'Edit data master satuan';
            }

            DB::commit();

            $this->activityLog->log([
                'user_id' => $userId,
                'action' => $action,
                'model_type' => Unit::class,
                'model_id' => $unitId,
                'old_values' => $oldData,
                'new_values' => $this->findOne($unitId)?->toArray(),
                'request_payload' => RequestAuditHelper::sanitize($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[UnitRepo][store] ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(int $id, int $userId): bool
    {
        $unit = Unit::find($id);
        if (!$unit || !$unit->canDelete()) {
            return false;
        }

        $oldData = $unit->toArray();

        DB::beginTransaction();
        try {
            $unit->delete();
            DB::commit();

            $this->activityLog->log([
                'user_id' => $userId,
                'action' => 'Hapus data master satuan dengan nama ' . $oldData['unit_name'],
                'model_type' => Unit::class,
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
            Log::error('[UnitRepo][destroy] ' . $e->getMessage());
            return false;
        }
    }
}
