<?php

namespace Icso\Accounting\Http\Requests;

use Icso\Accounting\Models\Manufacturing\ProductionOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateProductionOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->input('id') ?? $this->route('id');
        $table = (new ProductionOrder())->getTable();

        $rules = [
            'ref_no' => [
                'nullable',
                Rule::unique($table, 'ref_no')->ignore($id),
            ],
            'production_date' => ['required'],
            'warehouse_id' => ['required'],
            'product_id' => ['required'],
            'output_unit_id' => ['required'],
            'planned_qty' => ['required', 'numeric', 'gt:0'],
            'actual_qty' => ['nullable', 'numeric', 'gte:0'],
            'materials' => ['nullable', 'array'],
            'materials.*.product_id' => ['required_with:materials'],
            'materials.*.unit_id' => ['required_with:materials'],
            'materials.*.qty_actual' => ['nullable', 'numeric', 'gte:0'],
            'materials.*.qty_planned' => ['nullable', 'numeric', 'gte:0'],
            'results' => ['nullable', 'array'],
            'results.*.product_id' => ['required_with:results'],
            'results.*.unit_id' => ['required_with:results'],
            'results.*.qty_good' => ['nullable', 'numeric', 'gte:0'],
            'results.*.qty_waste' => ['nullable', 'numeric', 'gte:0'],
        ];

        if (empty($this->input('bom_id')) && empty($this->input('materials'))) {
            $rules['materials'][] = 'required';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'production_date.required' => 'Tanggal produksi masih kosong.',
            'warehouse_id.required' => 'Gudang produksi masih kosong.',
            'product_id.required' => 'Produk hasil masih kosong.',
            'output_unit_id.required' => 'Satuan hasil masih kosong.',
            'planned_qty.required' => 'Qty rencana produksi masih kosong.',
            'planned_qty.gt' => 'Qty rencana produksi harus lebih besar dari nol.',
            'materials.required' => 'Bahan produksi masih kosong jika BOM belum dipilih.',
            'materials.*.product_id.required_with' => 'Produk bahan pada salah satu item masih kosong.',
            'materials.*.unit_id.required_with' => 'Satuan bahan pada salah satu item masih kosong.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] = $validator->messages()->first();

        throw new HttpResponseException(response()->json($data));
    }
}
