<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Persediaan\Adjustment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return Adjustment::$rules;
    }

    public function messages()
    {
        return ['adjustment_date.required' => 'Tanggal penyesuaian Masih Kosong', 'warehouse_id.required' => 'Nama Gudang Masih Kosong',
            'coa_adjustment_id.required' => 'Akun penyesuaian Masih Kosong', 'adjustment_type.required' => 'Tipe penyesuaian','adjustmentproduct.required' => 'Daftar Barang Yang Akan disesuikan masih kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] = $validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
