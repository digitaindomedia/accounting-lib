<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPayment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesPaymentRequest extends FormRequest
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
        $id = $this->input('id') ?? $this->route('id');
        $paymentNo = $this->input('payment_no');
        $table = (new SalesPayment())->getTable();
        $baseRules = empty($id)
            ? SalesPayment::$rules
            : array_merge(
                SalesPayment::$rules,
                [
                    'payment_no' => [
                        'required',
                        Rule::unique($table, 'payment_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika payment_no tidak kosong
        if (empty($id) && !empty($paymentNo)) {
            $baseRules['payment_no'] = [
                Rule::unique($table, 'payment_no'),
            ];
        }

        return $baseRules;
    }

    public function messages()
    {
        return ['payment_date.required' => 'Tanggal Pelunasan Penjualan Masih Kosong',
            'payment_no.required' => 'Nomor pembayaran masih kosong.',
            'payment_no.unique' => 'Nomor pembayaran sudah digunakan.',
            'payment_method_id.required' => 'Metode Pembayaran Masih belum dipilih', 'invoice' => 'Daftar Invoice Yang Akan Dilunasi Masih Kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
