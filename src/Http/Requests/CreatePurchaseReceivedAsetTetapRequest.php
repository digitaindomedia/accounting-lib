<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseReceive;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePurchaseReceivedAsetTetapRequest extends FormRequest
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
        return PurchaseReceive::$rules;
    }

    public function messages()
    {
        return ['receive_date.required' => 'Tanggal Penerimaan Pembelian Aset Tetap Masih Kosong', 'order_id.required' => 'Nama Aset Belum dipilih'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
