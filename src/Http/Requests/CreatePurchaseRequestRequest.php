<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseRequestRequest extends FormRequest
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
        $requestNo = $this->input('request_no');
        $table = (new PurchaseRequest())->getTable();
        $baseRules = empty($id)
            ? PurchaseRequest::$rules
            : array_merge(
                PurchaseRequest::$rules,
                [
                    'request_no' => [
                        'required',
                        Rule::unique($table, 'request_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($requestNo)) {
            $baseRules['request_no'] = [
                Rule::unique($table, 'request_no'),
            ];
        }
        $baseRules['requestproduct.*.product_id'] = 'required|string';
        return $baseRules;
    }

    public function messages()
    {
        return ['request_date.required' => 'Tanggal permintaan Masih Kosong',
            'request_no.required' => 'Nomor permintaan masih kosong.',
            'request_no.unique' => 'Nomor permintaan sudah digunakan.',
            'requestproduct.required' => 'Daftar barang yang di minta Masih Kosong',
            'requestproduct.*.product_id.required' => 'Nama barang pada salah satu item masih kosong.'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
