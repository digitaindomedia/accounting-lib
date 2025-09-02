<?php

namespace Icso\Accounting\Http\Requests;

use Icso\Accounting\Models\Pembelian\Bast\PurchaseBast;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseBastRequest extends FormRequest
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
        $bastNo = $this->input('bast_no');
        $table = (new PurchaseBast())->getTable();
        $baseRules = empty($id)
            ? PurchaseBast::$rules
            : array_merge(
                PurchaseBast::$rules,
                [
                    'bast_no' => [
                        'required',
                        Rule::unique($table, 'bast_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika bast_no tidak kosong
        if (empty($id) && !empty($bastNo)) {
            $baseRules['bast_no'] = [
                Rule::unique($table, 'bast_no'),
            ];
        }

        return $baseRules;
    }

    public function messages()
    {
        return ['bast_date.required' => 'Tanggal BAST Masih Kosong',
            'bast_no.required' => 'Nomor BAST masih kosong.',
            'bast_no.unique' => 'Nomor BAST sudah digunakan.',
            'order_id.required' => 'Order pembelian masih belum dipilih', 'vendor_id' => 'Supplier masih belum dipilih', 'bastproduct' => 'Produk jasa Bast masih kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
