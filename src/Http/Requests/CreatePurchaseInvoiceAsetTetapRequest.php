<?php

namespace Icso\Accounting\Http\Requests;

use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseInvoice;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseInvoiceAsetTetapRequest extends FormRequest
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
        $refNo = $this->input('invoice_no');
        $table = (new PurchaseInvoice())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_INVOICE_PEMBELIAN_ASET_TETAP);
        $rules = PurchaseInvoice::$rules;
        if (empty($prefix)) {
            $rules['invoice_no'] = [
                'required',
                Rule::unique($table, 'invoice_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($refNo)) {
                // kalau user isi manual
                $rules['invoice_no'] = [
                    Rule::unique($table, 'invoice_no'),
                ];
            } else {
                // otomatis generate
                $rules['invoice_no'] = ['nullable'];
            }
        }
        else {
            $rules['invoice_no'] = [
                'required',
                Rule::unique($table, 'invoice_no')->ignore($id),
            ];
        }
        return $rules;
    }

    public function messages()
    {
        return ['invoice_date.required' => 'Tanggal Invoice Pembelian Aset Tetap Masih Kosong',
            'invoice_no.required' => 'Nomor Invoice belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan',
            'total_tagihan.required' => 'Total tagihan masih kosong','total_tagihan.gt' => 'Total tagihan tidak boleh 0'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
