<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\AsetTetap\Pembelian\PurchasePayment;
use Icso\Accounting\Models\AsetTetap\Penjualan\SalesInvoice;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\Utility;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesInvoiceAsetTetapRequest extends FormRequest
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
            'price' => Utility::remove_commas($this->input('price'))
        ]);
        $id = $this->input('id') ?? $this->route('id');
        $refNo = $this->input('sales_no');
        $table = (new SalesInvoice())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_SALES_INVOICE_ASET_TETAP);
        $rules = SalesInvoice::$rules;
        if (empty($prefix)) {
            $rules['sales_no'] = [
                'required',
                Rule::unique($table, 'sales_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($refNo)) {
                // kalau user isi manual
                $rules['sales_no'] = [
                    Rule::unique($table, 'sales_no'),
                ];
            } else {
                // otomatis generate
                $rules['sales_no'] = ['nullable'];
            }
        }
        else {
            $rules['sales_no'] = [
                'required',
                Rule::unique($table, 'sales_no')->ignore($id),
            ];
        }
        return $rules;
    }

    public function messages()
    {
        return ['sales_date.required' => 'Tanggal Penjualan Masih Kosong',
            'sales_no.required' => 'Nomor invoice penjualan belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan',
            'price.gt' => 'Total penjualan tidak boleh 0',
            'aset_tetap_id.required' => 'Nama aset belum dipilih',
            'profit_loss_coa_id.required' => 'Akun laba rugi belum dipilih',
            'price.required' => 'Harga jual masih kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
