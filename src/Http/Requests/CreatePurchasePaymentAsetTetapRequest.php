<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\AsetTetap\Pembelian\PurchasePayment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePurchasePaymentAsetTetapRequest extends FormRequest
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
        return PurchasePayment::$rules;
    }

    public function messages()
    {
        return ['payment_date.required' => 'Tanggal Pelunasan Pembelian Masih Kosong', 'payment_method_id.required' => 'Metode Pembayaran Masih belum dipilih', 'invoice_id' => 'Daftar Invoice Yang Akan Dilunasi Belum Dipilih','total.required' => "Total Pelunasan Masih Kosong"];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
