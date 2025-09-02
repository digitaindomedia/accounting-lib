<?php

namespace Icso\Accounting\Exports;

use Icso\Accounting\Repositories\Utils\SettingRepo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JurnalTransaksiExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return collect($this->data)->map(function ($item) {
            return [
                'tanggal' => $item->transaction_date,
                'no_ref' => $item->transaction_no,
                'akun' => $item->coa->coa_name." ".$item->coa->coa_code, // Assuming 'coa' relationship is loaded and 'nama' is the account name
                'keterangan' => $item->note,
                'debet' => number_format($item->debet, SettingRepo::getSeparatorFormat()),
                'kredit' => number_format($item->kredit, SettingRepo::getSeparatorFormat())
            ];
        });
    }

    public function getData()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'No Ref',
            'Akun',
            'Keterangan',
            'Debet',
            'Kredit',
        ];
    }
    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('E')->getAlignment()->setHorizontal('right');
        $sheet->getStyle('F')->getAlignment()->setHorizontal('right');
    }
}
