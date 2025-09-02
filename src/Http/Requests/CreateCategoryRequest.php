<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Master\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateCategoryRequest extends FormRequest
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
        $table = (new Category)->getTable();
        if(empty($id)){
            return array_merge(
                Category::$rules,
                [
                    'category_name' => "required|unique:$table,category_name",
                ]
            );
        }
        return array_merge(
            Category::$rules,
            [
                'category_name' => [
                    'required',
                    Rule::unique($table, 'category_name')->where(function ($query) {
                        return $query->whereRaw('LOWER(category_name) = ?', [strtolower($this->category_name)]);
                    })->ignore($id), // abaikan baris sendiri
                ],
            ]
        );
    }

    public function messages()
    {
        return [
            'category_name.required' => 'Nama Kategori Masih Kosong',
            'category_name.unique' => 'Nama Kategori sudah ada (tidak boleh sama walau beda huruf besar/kecil)',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
