<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseReceive;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseReceivedAsetTetapRequest extends FormRequest
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
        $recNo = $this->input('receive_no');
        $table = (new PurchaseReceive())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_PENERIMAAN_PEMBELIAN_ASET_TETAP);
        $rules = PurchaseReceive::$rules;
        if (empty($prefix)) {
            $rules['receive_no'] = [
                'required',
                Rule::unique($table, 'receive_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($recNo)) {
                // kalau user isi manual
                $rules['receive_no'] = [
                    Rule::unique($table, 'receive_no'),
                ];
            } else {
                // otomatis generate
                $rules['receive_no'] = ['nullable'];
            }
        }
        else {
            $rules['receive_no'] = [
                'required',
                Rule::unique($table, 'receive_no')->ignore($id),
            ];
        }
        return $rules;
    }

    public function messages()
    {
        return ['receive_date.required' => 'Tanggal Penerimaan Pembelian Aset Tetap Masih Kosong',
            'receive_no.required' => 'Nomor penerimaan belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'order_id.required' => 'Nama Aset Belum dipilih'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
