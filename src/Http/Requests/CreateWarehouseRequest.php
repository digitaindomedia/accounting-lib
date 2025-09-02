<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Master\Warehouse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateWarehouseRequest extends FormRequest
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
        $table = (new Warehouse)->getTable();
        if(empty($id)){
            return array_merge(
                Warehouse::$rules,
                [
                    'warehouse_code' => "required|unique:$table,warehouse_code",
                ]
            );
        }
        return array_merge(
            Warehouse::$rules,
            [
                'warehouse_code' => [
                    'required',
                    Rule::unique($table, 'warehouse_code')->ignore($id), // abaikan baris sendiri
                ],
            ]
        );
    }

    public function messages()
    {
        return ['warehouse_name.required' => 'Nama Gudang Masih Kosong',
            'warehouse_code.required' => 'Kode Gudang Masih Kosong',
            'warehouse_code.unique' => 'Kode Gudang sudah digunakan'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
