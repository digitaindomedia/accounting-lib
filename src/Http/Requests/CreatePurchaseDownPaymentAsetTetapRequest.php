<?php

namespace Icso\Accounting\Http\Requests;

use App\Models\Tenant\AsetTetap\Pembelian\PurchaseDownPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePurchaseDownPaymentAsetTetapRequest extends FormRequest
{
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
        return PurchaseDownPayment::$rules;
    }

    public function messages()
    {
        return ['downpayment_date.required' => 'Tanggal Uang Muka Masih Kosong', 'nominal.required' => 'Nominal uang muka Masih Kosong', 'order_id.required' => 'Order pembelian aset tetap masih belum dipilih'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
