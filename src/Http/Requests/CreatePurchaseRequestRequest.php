<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequest;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseRequestRequest extends FormRequest
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
        $requestNo = $this->input('request_no');
        $table = (new PurchaseRequest())->getTable();

        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_PERMINTAAN_PEMBELIAN);
        $rules = PurchaseRequest::$rules;
        if (empty($prefix)) {
            $rules['request_no'] = [
                'required',
                Rule::unique($table, 'request_no')->ignore($id),
            ];
        }

        // create + prefix tersedia → nomor optional, generate otomatis
        elseif (empty($id)) {
            if (!empty($requestNo)) {
                // kalau user isi manual
                $rules['request_no'] = [
                    Rule::unique($table, 'request_no'),
                ];
            } else {
                // otomatis generate
                $rules['request_no'] = ['nullable'];
            }
        }

        // update + prefix tersedia → nomor wajib ada
        else {
            $rules['request_no'] = [
                'required',
                Rule::unique($table, 'request_no')->ignore($id),
            ];
        }

        $rules['requestproduct.*.product_id'] = 'required|string';
        return $rules;
    }

    public function messages()
    {
        return ['request_date.required' => 'Tanggal permintaan Masih Kosong',
            'request_no.required' => 'Nomor permintaan belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'request_no.unique' => 'Nomor permintaan sudah digunakan.',
            'requestproduct.required' => 'Daftar barang yang di minta Masih Kosong',
            'requestproduct.*.product_id.required' => 'Nama barang pada salah satu item masih kosong.'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
