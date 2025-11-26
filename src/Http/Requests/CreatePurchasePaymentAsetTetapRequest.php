<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\AsetTetap\Pembelian\PurchasePayment;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\Utility;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchasePaymentAsetTetapRequest extends FormRequest
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
            'total' => Utility::remove_commas($this->input('total'))
        ]);
        $id = $this->input('id') ?? $this->route('id');
        $refNo = $this->input('payment_no');
        $table = (new PurchasePayment())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_PELUNASAN_PEMBELIAN_ASET_TETAP);
        $rules = PurchasePayment::$rules;
        if (empty($prefix)) {
            $rules['payment_no'] = [
                'required',
                Rule::unique($table, 'payment_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($refNo)) {
                // kalau user isi manual
                $rules['payment_no'] = [
                    Rule::unique($table, 'payment_no'),
                ];
            } else {
                // otomatis generate
                $rules['payment_no'] = ['nullable'];
            }
        }
        else {
            $rules['payment_no'] = [
                'required',
                Rule::unique($table, 'payment_no')->ignore($id),
            ];
        }
        return $rules;
    }

    public function messages()
    {
        return ['payment_date.required' => 'Tanggal Pelunasan Pembelian Masih Kosong',
            'payment_method_id.required' => 'Metode Pembayaran Masih belum dipilih',
            'payment_no.required' => 'Nomor pembayaran belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan',
            'total.gt' => 'Total pembayaran tidak boleh 0',
            'invoice_id' => 'Daftar Invoice Yang Akan Dilunasi Belum Dipilih','total.required' => "Total Pelunasan Masih Kosong"];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
