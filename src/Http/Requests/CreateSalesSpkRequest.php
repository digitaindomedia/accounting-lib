<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Spk\SalesSpk;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesSpkRequest extends FormRequest
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
        $spkNo = $this->input('spk_no');
        $table = (new SalesSpk())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_SPK);
        $rules = SalesSpk::$rules;

        if (empty($prefix)) {
            $rules['spk_no'] = [
                'required',
                Rule::unique($table, 'spk_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($spkNo)) {
                // kalau user isi manual
                $rules['spk_no'] = [
                    Rule::unique($table, 'spk_no'),
                ];
            } else {
                // otomatis generate
                $rules['spk_no'] = ['nullable'];
            }
        }
        else {
            $rules['spk_no'] = [
                'required',
                Rule::unique($table, 'spk_no')->ignore($id),
            ];
        }

        $rules['spkproduct.*.qty'] = ['required', 'numeric', 'min:0'];
        return $rules;
    }

    public function messages()
    {
        return ['spk_date.required' => 'Tanggal SPK Masih Kosong',
            'spkproduct.required' => 'Daftar jasa SPK Masih Kosong',
            'spk_no.required' => 'Nomor SPK belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'spk_no.unique' => 'Nomor SPK sudah digunakan.',
            'order_id.required' => 'Order Penjualan Masih Belum diPilih',
            'vendor_id.required' => 'Customer Masih Kosong',
            'spkproduct.*.qty.numeric' => 'Kuantitas jasa harus berupa angka',
            'spkproduct.*.qty.min' => 'Kuantitas jasa tidak boleh kurang dari 0',
            'spkproduct' => 'Produk jasa masih kosong'
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
