<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateSalesDeliveryRequest extends FormRequest
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
        $deliveryNo = $this->input('delivery_no');
        $table = (new SalesDelivery())->getTable();
        $baseRules = empty($id)
            ? SalesDelivery::$rules
            : array_merge(
                SalesDelivery::$rules,
                [
                    'delivery_no' => [
                        'required',
                        Rule::unique($table, 'delivery_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($deliveryNo)) {
            $baseRules['delivery_no'] = [
                Rule::unique($table, 'delivery_no'),
            ];
        }
        $baseRules['deliveryproduct.*.qty'] = ['required', 'numeric', 'min:0'];
        return $baseRules;
    }

    public function messages()
    {
        return ['delivery_date.required' => 'Tanggal Delivery Masih Kosong',
            'order_id.required' => 'Order Pembelian Masih Kosong',
            'vendor_id.required' => 'Nama Customer Masih Kosong',
            'warehouse_id.required' => 'Nama Gudang Masih Kosong' ,
            'deliveryproduct.required' => 'Daftar barang yang akan dikirim Masih Kosong',
            'deliveryproduct.*.qty.numeric' => 'Kuantitas barang harus berupa angka',
            'deliveryproduct.*.qty.required' => 'Kuantitas barang masih kosong',
            'deliveryproduct.*.qty.min' => 'Kuantitas barang tidak boleh kurang dari 0'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
