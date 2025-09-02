<?php
// app/Http/Requests/StoreRoleRequest.php
namespace Icso\Accounting\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRoleRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Update based on your authorization logic
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'tenant_id' => 'required|string|max:255',
            'permissions' => 'required|array',
            'permissions.*.group_id' => 'required|integer|exists:permissions_groups,id',
            'permissions.*.view' => 'boolean',
            'permissions.*.add' => 'boolean',
            'permissions.*.edit' => 'boolean',
            'permissions.*.delete' => 'boolean',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Nama rule wajib diisi.',
            'name.max' => 'Nama rule tidak boleh lebih dari 255 karakter.',
            'tenant_id.required' => 'Tenant ID wajib diisi.',
            'permissions.required' => 'Daftar permission wajib diisi.',
            'permissions.array' => 'Format permissions harus berupa array.',
            'permissions.*.group_id.required' => 'Group ID pada setiap permission wajib diisi.',
            'permissions.*.group_id.integer' => 'Group ID harus berupa angka.',
            'permissions.*.group_id.exists' => 'Group ID tidak ditemukan dalam database.',
            'permissions.*.view.boolean' => 'Nilai view harus berupa true atau false.',
            'permissions.*.add.boolean' => 'Nilai add harus berupa true atau false.',
            'permissions.*.edit.boolean' => 'Nilai edit harus berupa true atau false.',
            'permissions.*.delete.boolean' => 'Nilai delete harus berupa true atau false.',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        $data['status'] = false;
        $data['message'] =$validator->messages()->first();
        throw new HttpResponseException(response()->json($data));
    }
}
