<?php

namespace Icso\Accounting\Http\Requests;

use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseInvoice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePurchaseInvoiceAsetTetapRequest extends FormRequest
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
        return PurchaseInvoice::$rules;
    }

    public function messages()
    {
        return ['invoice_date.required' => 'Tanggal Invoice Pembelian Aset Tetap Masih Kosong', 'total_tagihan.required' => 'Total tagihan masih kosong','total_tagihan.gt' => 'Total tagihan tidak boleh 0'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
