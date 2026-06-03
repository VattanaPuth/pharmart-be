<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StatsSheet implements
    FromArray,
    WithTitle,
    WithHeadings,
    ShouldAutoSize,
    WithStyles
{
    public function __construct(private $stats) {}

    public function headings(): array
    {
        return [
            'Metric',
            'Value',
        ];
    }

    public function array(): array
    {
        return [
            ['Revenue', $this->stats['revenue'] ?? 0],
            ['Total Orders', $this->stats['total_orders'] ?? 0],
            ['Avg Order Value', $this->stats['avg_order_value'] ?? 0],
            ['Refund Amount', $this->stats['refund_amount'] ?? 0],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle('A1:B1')->applyFromArray([
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

        $sheet->getStyle('A2:B5')->applyFromArray([
            'font' => [
                'size' => 11,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | BORDERS
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle('A1:B5')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => 'thin',
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | MONEY FORMAT
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle('B2')->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');

        $sheet->getStyle('B4')->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');

        $sheet->getStyle('B5')->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');

        /*
        |--------------------------------------------------------------------------
        | HIGHLIGHT IMPORTANT ROWS
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle('A2:B2')->applyFromArray([
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => 'FCE7F3'],
            ],

            'font' => [
                'bold' => true,
            ],
        ]);

        $sheet->getStyle('A3:B3')->applyFromArray([
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => 'DBEAFE'],
            ],
        ]);

        return [];
    }

    public function title(): string
    {
        return 'Summary';
    }
}