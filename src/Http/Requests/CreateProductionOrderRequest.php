<?php

namespace Icso\Accounting\Http\Requests;

use Icso\Accounting\Models\Manufacturing\ProductionOrder;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
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
        $refNo = $this->input('ref_no');
        $table = (new ProductionOrder())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_PRODUCTION_ORDER);

        $rules = [
            'production_date' => ['required'],
            'warehouse_id' => ['required'],
            'product_id' => ['required'],
            'output_unit_id' => ['required'],
            'planned_qty' => ['required', 'numeric', 'gt:0'],
            'actual_qty' => ['nullable', 'numeric', 'gte:0'],
            'hpp_allocation_method' => ['nullable', 'in:qty,percentage'],
            'status_production' => ['nullable', 'in:draft,finished,cancelled'],
            'manual_material_override' => ['nullable', 'boolean'],
            'materials' => ['nullable', 'array'],
            'materials.*.material_source_type' => ['nullable', 'in:product,category'],
            'materials.*.product_id' => ['nullable'],
            'materials.*.source_product_id' => ['nullable'],
            'materials.*.category_id' => ['nullable'],
            'materials.*.source_category_id' => ['nullable'],
            'materials.*.unit_id' => ['required_with:materials'],
            'materials.*.qty_actual' => ['nullable', 'numeric', 'gte:0'],
            'materials.*.qty_planned' => ['nullable', 'numeric', 'gte:0'],
            'results' => ['nullable', 'array'],
            'results.*.product_id' => ['required_with:results'],
            'results.*.unit_id' => ['required_with:results'],
            'results.*.qty_planned' => ['nullable', 'numeric', 'gte:0'],
            'results.*.qty_good' => ['nullable', 'numeric', 'gte:0'],
            'results.*.qty_waste' => ['nullable', 'numeric', 'gte:0'],
            'results.*.hpp_allocation_percentage' => ['nullable', 'numeric', 'gte:0'],
        ];

        if (empty($prefix)) {
            $rules['ref_no'] = [
                'required',
                Rule::unique($table, 'ref_no')->ignore($id),
            ];
        } elseif (empty($id)) {
            $rules['ref_no'] = !empty($refNo)
                ? [Rule::unique($table, 'ref_no')]
                : ['nullable'];
        } else {
            $rules['ref_no'] = [
                'required',
                Rule::unique($table, 'ref_no')->ignore($id),
            ];
        }

        if (empty($this->input('bom_id')) && empty($this->input('materials'))) {
            $rules['materials'][] = 'required';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'ref_no.required' => 'Nomor produksi belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'ref_no.unique' => 'Nomor produksi sudah digunakan.',
            'production_date.required' => 'Tanggal produksi masih kosong.',
            'warehouse_id.required' => 'Gudang produksi masih kosong.',
            'product_id.required' => 'Produk hasil masih kosong.',
            'output_unit_id.required' => 'Satuan hasil masih kosong.',
            'planned_qty.required' => 'Qty rencana produksi masih kosong.',
            'planned_qty.gt' => 'Qty rencana produksi harus lebih besar dari nol.',
            'hpp_allocation_method.in' => 'Metode alokasi HPP tidak valid.',
            'materials.required' => 'Bahan produksi masih kosong jika BOM belum dipilih.',
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
