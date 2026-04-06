<?php

namespace Icso\Accounting\Http\Requests;

use Icso\Accounting\Models\Manufacturing\Bom;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateBomRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id') ?? $this->route('id');
        $table = (new Bom())->getTable();

        return [
            'bom_name' => ['required'],
            'product_id' => ['required'],
            'output_unit_id' => ['required'],
            'output_qty' => ['required', 'numeric', 'gt:0'],
            'manufacturing_mode' => ['nullable', 'in:pre_produce,auto_consume,both'],
            'auto_consume_trigger' => ['nullable', 'in:invoice,delivery,both'],
            'bom_code' => [
                'nullable',
                Rule::unique($table, 'bom_code')->ignore($id),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required'],
            'items.*.unit_id' => ['required'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages()
    {
        return [
            'bom_name.required' => 'Nama BOM masih kosong.',
            'product_id.required' => 'Produk hasil masih kosong.',
            'output_unit_id.required' => 'Satuan hasil masih kosong.',
            'output_qty.required' => 'Kuantitas hasil masih kosong.',
            'output_qty.gt' => 'Kuantitas hasil harus lebih besar dari nol.',
            'items.required' => 'Daftar bahan BOM masih kosong.',
            'items.*.product_id.required' => 'Produk bahan pada salah satu item masih kosong.',
            'items.*.unit_id.required' => 'Satuan bahan pada salah satu item masih kosong.',
            'items.*.qty.required' => 'Kuantitas bahan pada salah satu item masih kosong.',
            'items.*.qty.gt' => 'Kuantitas bahan harus lebih besar dari nol.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] = $validator->messages()->first();

        throw new HttpResponseException(response()->json($data));
    }
}
