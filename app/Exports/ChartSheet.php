<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChartSheet implements
    FromArray,
    WithTitle,
    WithHeadings,
    ShouldAutoSize,
    WithStyles
{
    public function __construct(private $chart) {}

    public function headings(): array
    {
        return [
            'Period',
            'Revenue ($)',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->chart as $c) {

            $rows[] = [
                $c['name'],
                $c['revenue'],
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->chart) + 1;

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("A1:B1")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],

            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => 'F06292'],
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | BODY
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("A2:B{$lastRow}")->applyFromArray([
            'font' => [
                'size' => 11,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | BORDERS
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("A1:B{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => 'thin',
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | COLUMN ALIGNMENT
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("B2:B{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');

        return [];
    }

    public function title(): string
    {
        return 'Revenue Chart';
    }
}