<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseOrder;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Utils\KeyNomor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreatePurchaseOrderAsetTetapRequest extends FormRequest
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
        $orderNo = $this->input('no_aset');
        $table = (new PurchaseOrder())->getTable();
        $prefix = SettingRepo::getOptionValue(KeyNomor::NO_ORDER_PEMBELIAN_ASET_TETAP);
        $rules = PurchaseOrder::$rules;
        if (empty($prefix)) {
            $rules['no_aset'] = [
                'required',
                Rule::unique($table, 'no_aset')->ignore($id),
            ];
        }
        elseif (empty($id)) {
            if (!empty($orderNo)) {
                // kalau user isi manual
                $rules['no_aset'] = [
                    Rule::unique($table, 'no_aset'),
                ];
            } else {
                // otomatis generate
                $rules['no_aset'] = ['nullable'];
            }
        }
        else {
            $rules['no_aset'] = [
                'required',
                Rule::unique($table, 'no_aset')->ignore($id),
            ];
        }

        return $rules;
    }

    public function messages()
    {
        return ['aset_tetap_date.required' => 'Tanggal Order Pembelian Aset Tetap Masih Kosong',
            'no_aset.required' => 'Nomor order belum bisa digenerate otomatis, silakan isi manual atau atur prefix nomor di pengaturan.',
            'nama_aset.required' => 'Nama Aset Masih Kosong',
            'aset_tetap_coa_id.required' => 'Akun Aset Masih Kosong',
            'harga_beli.required' => 'Harga beli masih kosong','harga_beli.gt' => 'Harga beli tidak boleh 0', 'qty.required' => 'Qty masih kosong','qty.gt' => 'Qty tidak boleh 0'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
