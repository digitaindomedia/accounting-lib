<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Retur\SalesRetur;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesReturRequest extends FormRequest
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
        $returNo = $this->input('retur_no');
        $table = (new SalesRetur())->getTable();
        $baseRules = empty($id)
            ? SalesRetur::$rules
            : array_merge(
                SalesRetur::$rules,
                [
                    'retur_no' => [
                        'required',
                        Rule::unique($table, 'retur_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($returNo)) {
            $baseRules['retur_no'] = [
                Rule::unique($table, 'retur_no'),
            ];
        }

        return $baseRules;
    }

    public function messages()
    {
        return ['retur_date.required' => 'Tanggal Retur Masih Kosong',
            'retur_no.required' => 'Nomor retur masih kosong.',
            'retur_no.unique' => 'Nomor retur sudah digunakan.',
            'returproduct.required' => 'Daftar barang yang akan diretur Masih Kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
