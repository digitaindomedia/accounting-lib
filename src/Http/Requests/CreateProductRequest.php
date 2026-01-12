<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Master\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
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
        $table = (new Product)->getTable();
        if (empty($id)) {
            // ===== CREATE =====
            return Product::$rules;
        }

        // ===== UPDATE =====
        return array_merge(
            Product::$rules,
            [
                'item_code' => [
                    'required',
                    Rule::unique($table, 'item_code')->ignore($id), // abaikan baris sendiri
                ],
            ]
        );

    }

    public function messages()
    {
        return [
            'item_name.required' => 'Nama Produk Masih Kosong',
            'unit_id.required' => 'Nama Satuan Masih Kosong.',
            'category.required' => 'Kategori Masih Kosong.',
            'item_code.required' => 'Kode Product masih kosong.',
            'item_code.unique'   => 'Kode Product sudah dipakai.',
        ];

    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
