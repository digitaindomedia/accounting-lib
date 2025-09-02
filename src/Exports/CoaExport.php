<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Models\Master\Coa;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CoaExport implements FromArray, WithHeadings
{

    public function array(): array
    {
        // Ambil data COA level 1, dan preload semua anak hingga level 4
        $data = Coa::where('coa_level', 1)
            ->with('children.children.children')
            ->get();

        $flattened = [];

        // Proses semua data root
        foreach ($data as $coa) {
            $this->flattenCoa($coa, $flattened);
        }

        return $flattened;
    }

    // Fungsi rekursif untuk "meratakan" data COA bertingkat jadi baris datar
    private function flattenCoa($coa, &$result)
    {
        // Pakai indentasi 4 spasi dikali level - 1
        $indent = str_repeat('    ', max(0, $coa->coa_level - 1));

        // Gabungkan kode dan nama akun
        $label = trim(($coa->coa_code ? $coa->coa_code . ' ' : '') . $coa->coa_name);

        // Tambahkan ke hasil
        $result[] = [
            'Nama COA' => $indent . $label
        ];

        // Jika punya anak, proses secara rekursif
        if ($coa->children && count($coa->children) > 0) {
            foreach ($coa->children as $child) {
                $this->flattenCoa($child, $result);
            }
        }
    }

    // Header Excel
    public function headings(): array
    {
        return ['Nama COA'];
    }
}
