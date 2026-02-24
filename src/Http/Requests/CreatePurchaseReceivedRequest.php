<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\Utility;
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
        $receivedNo = $this->input('receive_no');
        $table = (new PurchaseReceived())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_PENERIMAAN_PEMBELIAN);
        $rules = PurchaseReceived::$rules;

        if (empty($prefix)) {
            $rules['receive_no'] = [
                'required',
                Rule::unique($table, 'receive_no')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($receivedNo)) {
                // kalau user isi manual
                $rules['receive_no'] = [
                    Rule::unique($table, 'receive_no'),
                ];
            } else {
                // otomatis generate
                $rules['receive_no'] = ['nullable'];
            }
        }

        // update + prefix tersedia → nomor wajib ada
        else {
            $rules['receive_no'] = [
                'required',
                Rule::unique($table, 'receive_no')->ignore($id),
            ];
        }

        $rules['receiveproduct.*.qty'] = ['required', 'gt:0'];
        return $rules;
    }

    protected function prepareForValidation()
    {
        if ($this->has('receiveproduct')) {
            $products = $this->input('receiveproduct');

            foreach ($products as $i => $product) {
                if (isset($product['qty'])) {
                    $products[$i]['qty'] = Utility::remove_commas($product['qty']);
                }
            }

            $this->merge([
                'receiveproduct' => $products
            ]);
        }
    }

    public function messages()
    {
        return ['receive_date.required' => 'Tanggal Penerimaan Pembelian Masih Kosong',
            'receive_no.required' => 'Nomor penerimaan belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'receive_no.unique' => 'Nomor penerimaan sudah digunakan.',
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
