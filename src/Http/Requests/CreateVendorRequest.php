<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Master\Vendor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateVendorRequest extends FormRequest
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
        $table = (new Vendor)->getTable();
        if(empty($id)){
            return array_merge(
                Vendor::$rules,
                [
                    'vendor_code' => "unique:$table,vendor_code",
                ]
            );
        }
        return array_merge(
            Vendor::$rules,
            [
                'vendor_code' => [
                    'required',
                    Rule::unique($table, 'vendor_code')->ignore($id), // abaikan baris sendiri
                ],
            ]
        );
    }

    public function messages()
    {
        return ['vendor_name.required' => 'Nama Masih Kosong',
            'vendor_company_name.required' => 'Nama perusahaan Masih Kosong',
            'vendor_code.unique' => 'Kode sudah digunakan'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
