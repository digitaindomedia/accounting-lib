<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Pembelian\Order\PurchaseOrder;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\ProductType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseOrderRequest extends FormRequest
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
        $orderType = $this->input('order_type');
        $orderNo = $this->input('order_no');
        $table = (new PurchaseOrder())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_ORDER_PEMBELIAN);
        $rules = PurchaseOrder::$rules;
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
        if ($orderType == ProductType::SERVICE) {
            $rules['orderproduct.*.service_name'] = 'required|string';
        }
        if( $orderType == ProductType::ITEM){
            $rules['orderproduct.*.product_id'] = 'required|string';
        }
        $rules['orderproduct.*.qty'] = ['required', 'gt:0'];
        return $rules;
    }

    public function messages()
    {
        return [
            'order_date.required' => 'Tanggal Order Pembelian masih kosong.',
            'vendor_id.required' => 'Supplier masih kosong.',
            'orderproduct.required' => 'Daftar transaksi Order Pembelian masih kosong.',
            'order_no.required' => 'Nomor order belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'orderproduct.*.product_id.required' => 'Nama barang pada salah satu item masih kosong.',
            'order_no.unique' => 'Nomor Order Pembelian sudah digunakan.',
            'orderproduct.*.qty.gt' => 'Kuantitas barang tidak boleh kurang dari 0',
            'orderproduct.*.qty.required' => 'Kuantitas barang masih kosong',
            'orderproduct.*.service_name.required' => 'Nama jasa pada salah satu item masih kosong.',
            'orderproduct.*.service_name.string' => 'Nama jasa harus berupa teks.',
        ];


    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
