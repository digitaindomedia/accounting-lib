<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Enums\InvoiceTypeEnum;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseInvoiceRequest extends FormRequest
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
        $invoiceType = $this->input('invoice_type');
        $invoiceNo = $this->input('invoice_no');
        $orderId = $this->input('order_id');
        $table = (new PurchaseInvoicing())->getTable();

        // Base rules
        $rules = PurchaseInvoicing::$rules;

        // Jika UPDATE
        if (!empty($id)) {
            $rules['invoice_no'] = [
                'required',
                Rule::unique($table, 'invoice_no')->ignore($id),
            ];
            return $rules;
        }

        // Jika CREATE
        // Tambahkan validasi warehouse_id jika tipe invoice ITEM dan tidak ada order_id
        if ($invoiceType === InvoiceTypeEnum::ITEM->toString() && empty($orderId)) {
            $rules['warehouse_id'] = ['required'];
            $rules['orderproduct'] = 'required|string';
            $rules['orderproduct.*.product_id'] = 'required|string';
        }

        // Tambahkan validasi unique jika invoice_no tidak kosong (manual input)
        if (!empty($invoiceNo)) {
            $rules['invoice_no'] = [
                Rule::unique($table, 'invoice_no'),
            ];
        }

        return $rules;

    }

    public function messages()
    {
        return ['invoice_date.required' => 'Tanggal Invoice Masih Kosong',
            'invoice_no.required' => 'Nomor invoice masih kosong.',
            'vendor_id.required' => 'Supplier masih belum dipilih',
            'orderproduct.required' => 'Daftar transaksi Invoice Pembelian masih kosong.',
            'warehouse_id.required' => 'Gudang masih belum dipilih',
            'orderproduct.*.product_id.required' => 'Nama barang pada salah satu item masih kosong.',
            'invoice_no.unique'   => 'No Invoice sudah ada yang dipakai.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
