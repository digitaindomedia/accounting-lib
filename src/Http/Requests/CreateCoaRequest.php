<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Master\Coa;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateCoaRequest extends FormRequest
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
        $coaLevel = $this->input('coa_level');
        $table = (new Coa)->getTable();

        if (empty($id)) {
            if($coaLevel != 0)
            {
                return array_merge(
                    Coa::$rules,
                    [
                        'coa_code' => 'required|unique:als_coa,coa_code',
                        'coa_name' => 'required|unique:als_coa,coa_name',
                    ]
                );
            } else {
                return array_merge(
                    Coa::$rules,
                    [
                        'coa_name' => 'required|unique:als_coa,coa_name',
                    ]
                );
            }
            // ===== CREATE =====

        }

        // ===== UPDATE =====
        return array_merge(
            Coa::$updateRules,
            [
                'coa_code' => [
                    'required',
                    Rule::unique($table, 'coa_code')->ignore($id), // abaikan baris sendiri
                ],
            ]
        );
    }

    public function messages()
    {
        return [
            'coa_name.required' => 'Nama COA masih kosong.',
            'head_coa.required' => 'Head COA masih belum dipilih.',
            'coa_code.required' => 'Kode COA masih kosong.',
            'coa_code.unique'   => 'Kode COA sudah dipakai.',
            'coa_name.unique'   => 'Nama COA sudah dipakai.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
