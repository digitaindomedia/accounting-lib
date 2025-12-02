<?php

namespace Icso\Accounting\Http\Requests;

use Icso\Accounting\Models\Persediaan\StockUsage;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
class CreatePemakaianRequest extends FormRequest
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
        $table = (new StockUsage())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_PEMAKAIAN_STOCK);
        $rules = StockUsage::$rules;

        if (empty($prefix)) {
            $rules['ref_no'] = [
                'required',
                Rule::unique($table, 'ref_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($refNo)) {
                $rules['ref_no'] = [
                    Rule::unique($table, 'ref_no'),
                ];
            }else {
                // otomatis generate
                $rules['ref_no'] = ['nullable'];
            }
        }
        else {
            $rules['ref_no'] = [
                'required',
                Rule::unique($table, 'ref_no')->ignore($id),
            ];
        }

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($refNo)) {
            $rules['ref_no'] = [
                Rule::unique($table, 'ref_no'),
            ];
        }
        $rules['stockusageproduct.*.qty'] = ['required', 'numeric', 'min:0'];
        $rules['stockusageproduct.*.product_id'] = 'required';
        return $rules;
    }

    public function messages()
    {
        return ['usage_date.required' => 'Tanggal Pemakaian Masih Kosong',
            'stockusageproduct.required' => 'Daftar Produk Pemakaian Masih Kosong',
            'stockusageproduct.*.product_id.required' => 'Nama barang pada salah satu item masih kosong.',
            'stockusageproduct.*.qty.numeric' => 'Kuantitas barang harus berupa angka',
            'stockusageproduct.*.qty.min' => 'Kuantitas barang tidak boleh kurang dari 0',
            'stockusageproduct.*.qty.required' => 'Kuantitas barang masih kosong',
            'warehouse_id.required' => 'Nama Gudang Masih Belum diPilih'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
