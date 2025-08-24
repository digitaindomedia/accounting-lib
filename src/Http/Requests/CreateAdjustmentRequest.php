<?php

namespace Als\Accounting\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateAdjustmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'adjustment_date' => 'required|date',
            'warehouse_id' => 'required|integer',
            'coa_adjustment_id' => 'required|integer',
            'adjustment_type' => 'required|string',
            'adjustmentproduct' => 'required|array|min:1',
        ];
    }

    public function messages()
    {
        return [
            'adjustment_date.required' => 'Tanggal penyesuaian masih kosong',
            'warehouse_id.required' => 'Nama gudang masih kosong',
            'coa_adjustment_id.required' => 'Akun penyesuaian masih kosong',
            'adjustment_type.required' => 'Tipe penyesuaian masih kosong',
            'adjustmentproduct.required' => 'Daftar barang masih kosong',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] = $validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
