<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseReceivedRequest extends FormRequest
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
        $receivedNo = $this->input('received_no');
        $table = (new PurchaseReceived())->getTable();
        $baseRules = empty($id)
            ? PurchaseReceived::$rules
            : array_merge(
                PurchaseReceived::$rules,
                [
                    'received_no' => [
                        'required',
                        Rule::unique($table, 'received_no')->ignore($id),
                    ],
                ]
            );

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($receivedNo)) {
            $baseRules['received_no'] = [
                Rule::unique($table, 'received_no'),
            ];
        }
        return $baseRules;
    }

    public function messages()
    {
        return ['receive_date.required' => 'Tanggal Penerimaan Pembelian Masih Kosong',
            'received_no.required' => 'Nomor penerimaan masih kosong.',
            'received_no.unique' => 'Nomor penerimaan sudah digunakan.',
            'warehouse_id.required' => 'Nama Gudang Masih Belum dipilih',
            'order_id.required' => 'Order Pembelian Belum dipilih', 'receiveproduct.required' => 'Daftar barang yang akan diterima masih kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
