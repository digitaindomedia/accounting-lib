<?php

namespace Icso\Accounting\Http\Requests;



use Icso\Accounting\Models\Persediaan\StockUsage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePemakaianRequest extends FormRequest
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
        return StockUsage::$rules;
    }

    public function messages()
    {
        return ['usage_date.required' => 'Tanggal Pemakaian Masih Kosong',
            'stockusageproduct.required' => 'Daftar Produk Pemakaian Masih Kosong',
            'warehouse_id.required' => 'Nama Gudang Masih Belum diPilih'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
