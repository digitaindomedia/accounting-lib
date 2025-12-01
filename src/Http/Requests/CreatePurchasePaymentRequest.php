<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePayment;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchasePaymentRequest extends FormRequest
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
        $paymentNo = $this->input('payment_no');
        $table = (new PurchasePayment())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_PELUNASAN_PEMBELIAN);
        $rules = PurchasePayment::$rules;
        if (empty($prefix)) {
            $rules['payment_no'] = [
                'required',
                Rule::unique($table, 'payment_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($paymentNo)) {
                // kalau user isi manual
                $rules['invoice_no'] = [
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

        // Hanya tambahkan validasi unique untuk create jika payment_no tidak kosong
        if (empty($id) && !empty($paymentNo)) {
            $rules['payment_no'] = [
                Rule::unique($table, 'payment_no'),
            ];
        }

        return $rules;
    }

    public function messages()
    {
        return ['payment_date.required' => 'Tanggal Pelunasan Pembelian Masih Kosong',
            'payment_no.required' => 'Nomor pembayaran belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'payment_no.unique' => 'Nomor pembayaran sudah digunakan.',
            'payment_method_id.required' => 'Metode Pembayaran Masih belum dipilih', 'invoice' => 'Daftar Invoice Yang Akan Dilunasi Masih Kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
