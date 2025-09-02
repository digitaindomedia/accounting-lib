<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\AsetTetap\Penjualan\SalesInvoice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateSalesInvoiceAsetTetapRequest extends FormRequest
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
        return SalesInvoice::$rules;
    }

    public function messages()
    {
        return ['sales_date.required' => 'Tanggal Penjualan Masih Kosong', 'price.required' => 'Harga jual masih kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
