<?php
namespace Icso\Accounting\Http\Controllers\Master;


use Icso\Accounting\Models\Master\Country;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
class CountryController extends Controller
{
    public function getAllData(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        try
        {
            $model = new Country();
            $dataSet = $model->when(!empty($search), function ($query) use($search){
                $query->where('country_name', 'like', '%' .$search. '%');
                $query->orWhere('code1', 'like', '%' .$search. '%');
            });
            $data = $dataSet->orderBy('country_name','asc')->offset($page)->limit($perpage)->get();
            $total = $dataSet->count();
            $has_more = false;
            $page = $page + count($data);
            if($total > $page)
            {
                $has_more = true;
            }
            if($data)
            {
                $this->data['status'] = true;
                $this->data['message'] = 'Data berhasil ditemukan';
                $this->data['data'] = $data;
                $this->data['has_more'] = $has_more;
                $this->data['total'] = $total;

            }else{
                $this->data['status'] = false;
                $this->data['message'] = 'Data tidak ditemukan';
                $this->data['data'] = array();
                $this->data['has_more'] = $has_more;
            }
        } catch (\Exception $e) {
            $this->data['status'] = false;
            $this->data['message'] = 'Terjadi kesalahan';
        }
        return response()->json($this->data);
    }

    public function show(Request $request)
    {
        $id = $request->id;
        if(empty($id)){
            return response()->json(['status' => false, 'message' => 'Parameter input salah']);
        }
        else
        {
            try {
                $data = Country::where(array('id' => $id))->first();
                if($data)
                {
                    $this->data['status'] = true;
                    $this->data['message'] = 'data berhasil ditemukan';
                    $this->data['data'] = $data;

                }else{
                    $this->data['status'] = false;
                    $this->data['message'] = 'Data gagal ditemukan';
                }
            } catch (\Exception $e) {
                $this->data['status'] = false;
                $this->data['message'] = 'Terjadi kesalahan';
            }
        }
        return response()->json($this->data);
    }
}
