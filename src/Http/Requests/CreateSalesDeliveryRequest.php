<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
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
        $rules = SalesDelivery::$rules;
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_DELIVERY_ORDER);
        if (empty($prefix)) {
            $rules['delivery_no'] = [
                'required',
                Rule::unique($table, 'delivery_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($deliveryNo)) {
                // kalau user isi manual
                $rules['delivery_no'] = [
                    Rule::unique($table, 'delivery_no'),
                ];
            } else {
                // otomatis generate
                $rules['delivery_no'] = ['nullable'];
            }
        }

        // update + prefix tersedia → nomor wajib ada
        else {
            $rules['delivery_no'] = [
                'required',
                Rule::unique($table, 'delivery_no')->ignore($id),
            ];
        }

        $rules['deliveryproduct.*.qty'] = ['required'];
        return $rules;
    }

    public function messages()
    {
        return ['delivery_date.required' => 'Tanggal Delivery Masih Kosong',
            'order_id.required' => 'Order Pembelian Masih Kosong',
            'delivery_no.required' => 'Nomor pengiriman belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'vendor_id.required' => 'Nama Customer Masih Kosong',
            'warehouse_id.required' => 'Nama Gudang Masih Kosong' ,
            'deliveryproduct.required' => 'Daftar barang yang akan dikirim Masih Kosong',
            'deliveryproduct.*.qty.required' => 'Kuantitas barang masih kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
