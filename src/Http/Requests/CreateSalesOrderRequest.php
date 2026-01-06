<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesOrderRequest extends FormRequest
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
        $orderNo = $this->input('order_no');
        $table = (new SalesOrder())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_ORDER_PENJUALAN);
        $rules = SalesOrder::$rules;

        if (empty($prefix)) {
            $rules['order_no'] = [
                'required',
                Rule::unique($table, 'order_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($orderNo)) {
                // kalau user isi manual
                $rules['order_no'] = [
                    Rule::unique($table, 'order_no'),
                ];
            } else {
                // otomatis generate
                $rules['order_no'] = ['nullable'];
            }
        }

        // update + prefix tersedia → nomor wajib ada
        else {
            $rules['order_no'] = [
                'required',
                Rule::unique($table, 'order_no')->ignore($id),
            ];
        }

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($orderNo)) {
            $rules['order_no'] = [
                Rule::unique($table, 'order_no'),
            ];
        }

        // Tambahkan validasi service_name jika order_type SERVICE

        $rules['orderproduct.*.product_id'] = 'required|string';
        $rules['orderproduct.*.qty'] = ['required', 'gt:0'];
        return $rules;
    }

    public function messages()
    {
        return ['order_date.required' => 'Tanggal Order Penjualan Masih Kosong',
            'vendor_id.required' => 'Customer Masih Kosong',
            'order_no.required' => 'Nomor order belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'orderproduct.required' => 'Daftar Transaksi Order Penjualan Masih Kosong',
            'orderproduct.*.product_id.required' => 'Nama barang pada salah satu item masih kosong.',
            'orderproduct.*.qty.gt' => 'Kuantitas barang tidak boleh kurang dari sama dengan 0',
            'orderproduct.*.qty.required' => 'Kuantitas barang masih kosong',
            'order_no.unique' => 'Nomor Order Pembelian sudah digunakan.'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
