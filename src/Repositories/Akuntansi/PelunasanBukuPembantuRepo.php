<?php

namespace Icso\Accounting\Repositories\Akuntansi;


use Icso\Accounting\Models\Akuntansi\PelunasanBukuPembantu;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;

class PelunasanBukuPembantuRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PelunasanBukuPembantu $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $bukuPembantuId = $request->buku_pembantu_id;
        $refNo = $request->ref_no;
        $paymentDate = $request->payment_date;
        $note = !empty($request->note) ? $request->note : '';
        $jurnalId = $request->jurnal_id;
        $jurnalAkunId = $request->jurnal_akun_id;
        $nominal = Utility::remove_commas($request->nominal);
        $userId = $request->user_id;

        $arrData = array(
            'buku_pembantu_id' => $bukuPembantuId,
            'ref_no' => $refNo,
            'payment_date' => $paymentDate,
            'note' => $note,
            'jurnal_id' => $jurnalId,
            'jurnal_akun_id' => $jurnalAkunId,
            'nominal' => $nominal,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        );
        if(!empty($id)){
            $res = $this->update($arrData, $id);
        } else {
            $arrData['created_at'] = date('Y-m-d H:i:s');
            $arrData['created_by'] = $userId;
            $res = $this->create($arrData);
        }
        return $res;
    }

    public function getAllPaymentByBukuPembantuId($bukuPembantuId){
        $res = $this->findAllByWhere(array('buku_pembantu_id' => $bukuPembantuId));
        $total = 0;
        if(count($res) > 0)
        {
            foreach ($res as $pay)
            {
                $total = $total + $pay->nominal;
            }
        }
        return $total;
    }

}
