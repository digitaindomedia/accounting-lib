<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Master\Unit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateUnitRequest extends FormRequest
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
        $table = (new Unit)->getTable();
        if(empty($id)){
            return array_merge(
                Unit::$rules,
                [
                    'unit_code' => "required|unique:$table,unit_code",
                ]
            );
        }
        return array_merge(
            Unit::$rules,
            [
                'unit_code' => [
                    'required',
                    Rule::unique($table, 'unit_code')->ignore($id), // abaikan baris sendiri
                ],
            ]
        );
    }

    public function messages()
    {
        return ['unit_name.required' => 'Nama Satuan Masih Kosong',
            'unit_code.required' => 'Singkatan/kode Satuan Masih Kosong',
            'unit_code.unique' => 'Singkatan/kode Satuan sudah digunakan'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
