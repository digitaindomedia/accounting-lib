<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\UangMuka\SalesDownpayment;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\Utility;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesDpRequest extends FormRequest
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
        $this->merge([
            'nominal' => Utility::remove_commas($this->input('nominal'))
        ]);
        $id = $this->input('id') ?? $this->route('id');
        $refNo = $this->input('ref_no');
        $table = (new SalesDownpayment())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_UANG_MUKA_PENJUALAN);

        $rules = SalesDownpayment::$rules;
        if (empty($prefix)) {
            $rules['ref_no'] = [
                'required',
                Rule::unique($table, 'ref_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($refNo)) {
                $rules['ref_no'] = [
                    Rule::unique($table, 'ref_no'),
                ];
            }else {
                // otomatis generate
                $rules['ref_no'] = ['nullable'];
            }
        }
        else {
            $rules['ref_no'] = [
                'required',
                Rule::unique($table, 'ref_no')->ignore($id),
            ];
        }

        // Hanya tambahkan validasi unique untuk create jika ref_no tidak kosong
        if (empty($id) && !empty($refNo)) {
            $rules['ref_no'] = [
                Rule::unique($table, 'ref_no'),
            ];
        }

        return $rules;
    }

    public function messages()
    {
        return ['downpayment_date.required' => 'Tanggal Uang Muka Masih Kosong',
            'nominal.required' => 'Nominal uang muka Masih Kosong',
            'coa_id.required' => 'Akun Kas/Bank masih kosong.',
            'ref_no.unique' => 'Nomor uang muka sudah digunakan.','nominal.gt' => 'Nominal uang muka tidak boleh 0.',
            'order_id.required' => 'Order pembelian masih belum dipilih'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
