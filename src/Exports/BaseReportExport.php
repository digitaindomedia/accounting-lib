<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BaseReportExport implements FromView, ShouldAutoSize, WithEvents
{
    protected $data;
    protected $params;
    protected $viewName;

    public function __construct($data, $params, $viewName)
    {
        $this->data = $data;
        $this->params = $params;
        $this->viewName = $viewName;
    }

    public function view(): \Illuminate\Contracts\View\View
    {
        // Return the view with the data to be exported
        return view($this->viewName, [
            'data' => $this->data,
            'params' => $this->params
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
                $fullRange = "A1:{$highestColumn}{$highestRow}";

                $sheet->calculateColumnWidths();

                $sheet->getStyle($fullRange)->applyFromArray([
                    'font' => [
                        'name' => 'Calibri',
                        'size' => 11,
                        'color' => ['rgb' => '1F2937'],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_TOP,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);

                $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1D4ED8'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                if ($highestRow >= 2) {
                    $sheet->getStyle("A2:{$highestColumn}2")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '1E3A8A'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'DBEAFE'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                $headerKeywords = [
                    'Nomor Transaksi',
                    'Nama Customer',
                    'Nama Supplier',
                    'Nama Item',
                    'Qty',
                    'Harga',
                    'Subtotal',
                    'Grand Total',
                ];

                for ($row = 1; $row <= $highestRow; $row++) {
                    $rowText = '';
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $rowText .= ' ' . (string) $sheet->getCellByColumnAndRow($col, $row)->getValue();
                    }

                    $isHeader = false;
                    foreach ($headerKeywords as $keyword) {
                        if (stripos($rowText, $keyword) !== false) {
                            $isHeader = true;
                            break;
                        }
                    }

                    if ($isHeader) {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'color' => ['rgb' => '111827'],
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'E5E7EB'],
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],
                        ]);
                    }

                    if (stripos($rowText, 'Total') !== false) {
                        $sheet->getStyle("A{$row}:{$highestColumn}{$row}")->applyFromArray([
                            'font' => [
                                'bold' => true,
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F3F4F6'],
                            ],
                        ]);
                    }
                }

                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $column = Coordinate::stringFromColumnIndex($col);
                    $dimension = $sheet->getColumnDimension($column);
                    $dimension->setAutoSize(true);
                    $width = $dimension->getWidth();

                    if ($width < 12) {
                        $dimension->setAutoSize(false);
                        $dimension->setWidth(12);
                    } elseif ($width > 45) {
                        $dimension->setAutoSize(false);
                        $dimension->setWidth(45);
                    }
                }

                $this->applyKnownReportColumnWidths($sheet, $highestColumnIndex);

                $rightAlignedColumns = ['E', 'F', 'G', 'H', 'I', 'J'];
                foreach ($rightAlignedColumns as $column) {
                    if (Coordinate::columnIndexFromString($column) <= $highestColumnIndex) {
                        $sheet->getStyle("{$column}:{$column}")
                            ->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }

                if ($highestRow >= 4) {
                    $sheet->freezePane('A4');
                }
            },
        ];
    }

    private function applyKnownReportColumnWidths($sheet, int $highestColumnIndex): void
    {
        $widths = [];

        if ($this->viewName === 'accounting::sales.sales_invoice_detail_item_report') {
            $widths = [
                'A' => 20,
                'B' => 14,
                'C' => 24,
                'D' => 34,
                'E' => 16,
                'F' => 18,
                'G' => 18,
            ];
        }

        if ($this->viewName === 'accounting::sales.sales_invoice_detail_report') {
            $widths = [
                'A' => 8,
                'B' => 20,
                'C' => 14,
                'D' => 26,
                'E' => 34,
                'F' => 18,
                'G' => 18,
                'H' => 18,
                'I' => 18,
                'J' => 18,
            ];
        }

        foreach ($widths as $column => $width) {
            if (Coordinate::columnIndexFromString($column) <= $highestColumnIndex) {
                $sheet->getColumnDimension($column)->setAutoSize(false);
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        }
    }
}
