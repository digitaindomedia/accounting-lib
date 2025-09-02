<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
        return SalesDelivery::$rules;
    }

    public function messages()
    {
        return ['delivery_date.required' => 'Tanggal Delivery Masih Kosong','order_id.required' => 'Order Pembelian Masih Kosong', 'vendor_id.required' => 'Nama Customer Masih Kosong', 'warehouse_id.required' => 'Nama Gudang Masih Kosong' ,'deliveryproduct.required' => 'Daftar barang yang akan dikirim Masih Kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
