<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Persediaan\Adjustment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateAdjustmentRequest extends FormRequest
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
        $refNo = $this->input('ref_no');
        $table = (new Adjustment())->getTable();
        $baseRules = empty($id)
            ? Adjustment::$rules
            : array_merge(
                Adjustment::$rules,
                [
                    'ref_no' => [
                        'required',
                        Rule::unique($table, 'ref_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($refNo)) {
            $baseRules['ref_no'] = [
                Rule::unique($table, 'ref_no'),
            ];
        }
        $baseRules['adjustmentproduct.*.qty'] = ['required', 'numeric', 'min:0'];
        $baseRules['adjustmentproduct.*.product_id'] = 'required';
        return $baseRules;
    }

    public function messages()
    {
        return ['adjustment_date.required' => 'Tanggal penyesuaian Masih Kosong', 'warehouse_id.required' => 'Nama Gudang Masih Kosong',
            'coa_adjustment_id.required' => 'Akun penyesuaian Masih Kosong',
            'adjustmentproduct.*.product_id.required' => 'Nama barang pada salah satu item masih kosong.',
            'adjustmentproduct.*.qty.numeric' => 'Kuantitas barang harus berupa angka',
            'adjustmentproduct.*.qty.min' => 'Kuantitas barang tidak boleh kurang dari 0',
            'adjustment_type.required' => 'Tipe penyesuaian','adjustmentproduct.required' => 'Daftar Barang Yang Akan disesuikan masih kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] = $validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
