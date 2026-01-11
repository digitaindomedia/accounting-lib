<?php
namespace Icso\Accounting\Repositories\Master;


use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Utils\RequestAuditHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class PaymentMethodRepo extends ElequentRepository
{

    protected $model;
    protected ActivityLogService $activityLog;

    public function __construct(PaymentMethod $model, ActivityLogService $activityLog)
    {
        parent::__construct($model);
        $this->model = $model;
        $this->activityLog = $activityLog;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new PaymentMethod();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('payment_name', 'like', '%' .$search. '%');
            $query->orWhere('descriptions', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('payment_name','asc')->offset($page)->limit($perpage)->get();
        if(count($dataSet) > 0) {
            foreach ($dataSet as $item) {
                $findCoa = Coa::where(array('id' => $item->coa_id))->first();
                $item->coa = $findCoa;
            }
        }
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new PaymentMethod();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('payment_name', 'like', '%' .$search. '%');
            $query->orWhere('descriptions', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('payment_name','asc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $coa_id = $request->coa_id;
        $userId = $request->user_id;

        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id)?->toArray();
        }

        $arrData = array(
            'payment_name' => $request->payment_name,
            'descriptions' => (!empty($request->descriptions) ? $request->descriptions : ''),
            'coa_id' => $coa_id,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                // CREATE
                $arrData['created_by'] = $userId;
                $arrData['created_at'] = now();

                $result = $this->create($arrData);
                $paymentMethodId = $result->id;
                $action = 'Tambah data master metode pembayaran dengan nama '.$request->payment_name;
            } else {
                // UPDATE
                $this->update($arrData, $id);
                $paymentMethodId = $id;
                $action = 'Edit data master metode pembayaran dengan nama '.$request->payment_name;
            }

            DB::commit();

            // 🔥 ACTIVITY LOG
            $this->activityLog->log([
                'user_id'         => $userId,
                'action'          => $action,
                'model_type'      => PaymentMethod::class,
                'model_id'        => $paymentMethodId,
                'old_values'      => $oldData,
                'new_values'      => $this->findOne($paymentMethodId)?->toArray(),
                'request_payload' => RequestAuditHelper::sanitize($request),
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);

            return true;

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[PaymentMethodRepo][store] ' . $e->getMessage());
            return false;
        }
    }
}
