<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Spk\SalesSpk;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateSalesSpkRequest extends FormRequest
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
        return SalesSpk::$rules;
    }

    public function messages()
    {
        return ['spk_date.required' => 'Tanggal SPK Masih Kosong',
            'spkproduct.required' => 'Daftar jasa SPK Masih Kosong',
            'order_id.required' => 'Order Penjualan Masih Belum diPilih',
            'vendor_id.required' => 'Customer Masih Kosong',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
