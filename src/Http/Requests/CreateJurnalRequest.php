<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Akuntansi\Jurnal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateJurnalRequest extends FormRequest
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
        $table = (new Jurnal())->getTable();
        $rules = Jurnal::$rules;
        $rules['jurnal_no'] = [
            'required',
            Rule::unique($table, 'jurnal_no')->ignore($id),
        ];

        return $rules;
    }

    public function messages()
    {
        return [
            'jurnal_date.required' => 'Tanggal Jurnal Masih Kosong',
            'jurnal_akun.required' => 'Akun Transaksi Masih Kosong',
            'jurnal_no.required' => 'Nomor Jurnal Masih Kosong',
            'jurnal_no.unique' => 'Nomor Jurnal sudah digunakan',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
