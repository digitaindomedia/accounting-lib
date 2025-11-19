<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Spk\SalesSpk;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesSpkRequest extends FormRequest
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
        $bastNo = $this->input('spk_no');
        $table = (new SalesSpk())->getTable();
        $baseRules = empty($id)
            ? SalesSpk::$rules
            : array_merge(
                SalesSpk::$rules,
                [
                    'spk_no' => [
                        'required',
                        Rule::unique($table, 'spk_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika bast_no tidak kosong
        if (empty($id) && !empty($bastNo)) {
            $baseRules['bast_no'] = [
                Rule::unique($table, 'spk_no'),
            ];
        }

        return $baseRules;
    }

    public function messages()
    {
        return ['spk_date.required' => 'Tanggal SPK Masih Kosong',
            'spkproduct.required' => 'Daftar jasa SPK Masih Kosong',
            'spk_no.required' => 'Nomor SPK masih kosong.',
            'spk_no.unique' => 'Nomor SPK sudah digunakan.',
            'order_id.required' => 'Order Penjualan Masih Belum diPilih',
            'vendor_id.required' => 'Customer Masih Kosong',
            'spkproduct' => 'Produk jasa masih kosong'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
