<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePurchaseOrderAsetTetapRequest extends FormRequest
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
        return PurchaseOrder::$rules;
    }

    public function messages()
    {
        return ['aset_tetap_date.required' => 'Tanggal Order Pembelian Aset Tetap Masih Kosong', 'nama_aset.required' => 'Nama Aset Masih Kosong', 'harga_beli.required' => 'Harga beli masih kosong','harga_beli.gt' => 'Harga beli tidak boleh 0', 'qty.required' => 'Qty masih kosong','qty.gt' => 'Qty tidak boleh 0'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
