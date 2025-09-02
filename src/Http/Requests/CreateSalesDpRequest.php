<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\UangMuka\SalesDownpayment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateSalesDpRequest extends FormRequest
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
        return SalesDownpayment::$rules;
    }

    public function messages()
    {
        return ['downpayment_date.required' => 'Tanggal Uang Muka Masih Kosong', 'nominal.required' => 'Nominal uang muka Masih Kosong', 'order_id.required' => 'Order pembelian masih belum dipilih'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
