<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Master\Coa;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
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
        $coaLevel = $this->resolveCoaLevel();
        $table = (new Coa)->getTable();

        if (empty($id)) {
            $rules = array_merge(
                Coa::$rules,
                [
                    'coa_name' => 'required|unique:als_coa,coa_name',
                ]
            );

            if ($coaLevel === 4) {
                $rules['coa_code'] = 'required|unique:als_coa,coa_code';
            } elseif (!empty($this->input('coa_code'))) {
                $rules['coa_code'] = 'unique:als_coa,coa_code';
            }

            return $rules;
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
        Log::warning('[CreateCoaRequest][failedValidation] Validasi COA gagal', [
            'message' => $data['message'],
            'payload' => $this->except(['password', 'password_confirmation']),
        ]);
        throw new HttpResponseException(response()->json($data));
    }

    private function resolveCoaLevel(): int
    {
        if (!$this->hasSelectedParent($this->input('head_coa'))) {
            return 1;
        }

        if (!$this->hasSelectedParent($this->input('subhead_coa'))) {
            return 2;
        }

        if (!$this->hasSelectedParent($this->input('subhead_coa2'))) {
            return 3;
        }

        return 4;
    }

    private function hasSelectedParent($value): bool
    {
        return !in_array($value, [null, '', '0', 0], true);
    }
}
