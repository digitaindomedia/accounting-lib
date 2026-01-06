<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Retur\SalesRetur;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesReturRequest extends FormRequest
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
        $returNo = $this->input('retur_no');
        $table = (new SalesRetur())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_RETUR_PENJUALAN);

        $rules = SalesRetur::$rules;
        if (empty($prefix)) {
            $rules['retur_no'] = [
                'required',
                Rule::unique($table, 'retur_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($returNo)) {
                // kalau user isi manual
                $rules['retur_no'] = [
                    Rule::unique($table, 'retur_no'),
                ];
            } else {
                // otomatis generate
                $rules['retur_no'] = ['nullable'];
            }
        }
        else {
            $rules['retur_no'] = [
                'required',
                Rule::unique($table, 'retur_no')->ignore($id),
            ];
        }

        return $rules;
    }

    public function messages()
    {
        return ['retur_date.required' => 'Tanggal Retur Masih Kosong',
            'retur_no.required' => 'Nomor retur belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'retur_no.unique' => 'Nomor retur sudah digunakan.',
            'returproduct.required' => 'Daftar barang yang akan diretur Masih Kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
