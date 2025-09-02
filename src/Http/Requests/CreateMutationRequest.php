<?php

namespace Icso\Accounting\Http\Requests;


use Icso\Accounting\Models\Persediaan\Mutation;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateMutationRequest extends FormRequest
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
        return Mutation::$rules;
    }

    public function messages()
    {
        return ['mutation_date.required' => 'Tanggal mutasi Masih Kosong', 'ref_no.required' => 'No Transaksi Masih Kosong',
            'from_warehouse_id.required' => 'Dari Gudang Masih Kosong', 'to_warehouse_id.required' => 'Ke Gudang Masih Kosong','mutationproduct.required' => 'Daftar Barang Yang Akan dipindah masih kosong'];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] = $validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
