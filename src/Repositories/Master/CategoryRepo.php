<?php
namespace Icso\Accounting\Repositories\Master;

use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Models\Master\Category;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\RequestAuditHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CategoryRepo extends ElequentRepository{

    protected $model;
    protected ActivityLogService $activityLog;
    public function __construct(Category $model,ActivityLogService $activityLog)
    {
        parent::__construct($model);
        $this->model = $model;
        $this->activityLog = $activityLog;

    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new Category();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('category_name', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('category_name','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new Category();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('category_name', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('category_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id     = $request->id;
        $userId = $request->user_id;

        // 🔎 OLD DATA (for update)
        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id)?->toArray();
        }

        $arr = [
            'category_name'        => $request->category_name,
            'category_description' => $request->category_description ?? '',
            'updated_at'           => now(),
            'updated_by'           => $userId,
        ];

        DB::beginTransaction();
        try {
            if (empty($id)) {
                // CREATE
                $arr['category_type'] = $request->category_type;
                $arr['created_at']    = now();
                $arr['created_by']    = $userId;

                $result = $this->create($arr);
                $categoryId = $result->id;
                $action = 'Tambah data master kategori';
            } else {
                // UPDATE
                $this->update($arr, $id);
                $categoryId = $id;
                $action = 'Edit data master kategori';
            }

            DB::commit();

            // 🔥 ACTIVITY LOG
            $this->activityLog->log([
                'user_id'         => $userId,
                'action'          => $action,
                'model_type'      => Category::class,
                'model_id'        => $categoryId,
                'old_values'      => $oldData,
                'new_values'      => $this->findOne($categoryId)?->toArray(),
                'request_payload' => RequestAuditHelper::sanitize($request),
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[CategoryRepo][store] ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(int $id, int $userId): bool
    {
        $oldData = $this->findOne($id)?->toArray();

        DB::beginTransaction();
        try {
            $this->delete($id);
            DB::commit();

            // 🔥 ACTIVITY LOG
            $this->activityLog->log([
                'user_id'         => $userId,
                'action'          => 'Hapus data master kategori dengan nama '.$oldData->category_name,
                'model_type'      => Category::class,
                'model_id'        => $id,
                'old_values'      => $oldData,
                'new_values'      => null,
                'request_payload' => RequestAuditHelper::sanitize(request()),
                'ip_address'      => request()->ip(),
                'user_agent'      => request()->userAgent(),
            ]);

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[CategoryRepo][destroy] ' . $e->getMessage());
            return false;
        }
    }
}
