<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
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
        $baseRules = empty($id)
            ? SalesOrder::$rules
            : array_merge(
                SalesOrder::$rules,
                [
                    'order_no' => [
                        'required',
                        Rule::unique($table, 'order_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($orderNo)) {
            $baseRules['order_no'] = [
                Rule::unique($table, 'order_no'),
            ];
        }

        // Tambahkan validasi service_name jika order_type SERVICE

        $baseRules['orderproduct.*.product_id'] = 'required|string';
        $baseRules['orderproduct.*.qty'] = ['required', 'numeric', 'min:0'];
        return $baseRules;
    }

    public function messages()
    {
        return ['order_date.required' => 'Tanggal Order Penjualan Masih Kosong',
            'vendor_id.required' => 'Customer Masih Kosong',
            'orderproduct.required' => 'Daftar Transaksi Order Penjualan Masih Kosong',
            'orderproduct.*.product_id.required' => 'Nama barang pada salah satu item masih kosong.',
            'orderproduct.*.qty.numeric' => 'Kuantitas barang harus berupa angka',
            'orderproduct.*.qty.min' => 'Kuantitas barang tidak boleh kurang dari 0',
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
