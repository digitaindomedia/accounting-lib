<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
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
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_PENERIMAAN_PEMBELIAN);
        $rules = PurchaseReceived::$rules;

        if (empty($prefix)) {
            $rules['received_no'] = [
                'required',
                Rule::unique($table, 'received_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($receivedNo)) {
                // kalau user isi manual
                $rules['received_no'] = [
                    Rule::unique($table, 'received_no'),
                ];
            } else {
                // otomatis generate
                $rules['received_no'] = ['nullable'];
            }
        }

        // update + prefix tersedia → nomor wajib ada
        else {
            $rules['received_no'] = [
                'required',
                Rule::unique($table, 'received_no')->ignore($id),
            ];
        }

        // Hanya tambahkan validasi unique untuk create jika order_no tidak kosong
        if (empty($id) && !empty($receivedNo)) {
            $rules['received_no'] = [
                Rule::unique($table, 'received_no'),
            ];
        }
        $rules['receiveproduct.*.qty'] = ['required', 'gt:0'];
        return $rules;
    }

    public function messages()
    {
        return ['receive_date.required' => 'Tanggal Penerimaan Pembelian Masih Kosong',
            'received_no.required' => 'Nomor penerimaan belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'received_no.unique' => 'Nomor penerimaan sudah digunakan.',
            'warehouse_id.required' => 'Nama Gudang Masih Belum dipilih',
            'order_id.required' => 'Order Pembelian Belum dipilih', 'receiveproduct.required' => 'Daftar barang yang akan diterima masih kosong', 'receiveproduct.*.qty.required' => 'Kuantitas barang masih kosong',
            'receiveproduct.*.qty.gt' => 'Kuantitas barang tidak boleh kurang dari 1'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
