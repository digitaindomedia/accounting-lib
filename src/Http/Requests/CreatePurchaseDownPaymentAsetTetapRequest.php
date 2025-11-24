<?php

namespace Icso\Accounting\Http\Requests;

use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseDownPayment;
use Icso\Accounting\Utils\Utility;
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
        $this->merge([
            'nominal' => Utility::remove_commas($this->input('nominal'))
        ]);

        $id = $this->input('id') ?? $this->route('id');
        $refNo = $this->input('ref_no');
        $table = (new PurchaseDownPayment())->getTable();
        $baseRules = empty($id)
            ? PurchaseDownPayment::$rules
            : array_merge(
                PurchaseDownPayment::$rules,
                [
                    'ref_no' => [
                        'required',
                        Rule::unique($table, 'ref_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika ref_no tidak kosong
        if (empty($id) && !empty($refNo)) {
            $baseRules['ref_no'] = [
                Rule::unique($table, 'ref_no'),
            ];
        }

        return $baseRules;
    }

    public function messages()
    {
        return ['downpayment_date.required' => 'Tanggal Uang Muka Masih Kosong',
            'ref_no.required' => 'Nomor uang muka masih kosong.',
            'nominal.required' => 'Nominal uang muka Masih Kosong',
            'ref_no.unique' => 'Nomor uang muka sudah digunakan.',
            'nominal.gt' => 'Nominal uang muka tidak boleh 0.',
            'order_id.required' => 'Order pembelian aset tetap masih belum dipilih'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
