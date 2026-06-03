<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BreakdownSheet implements
    FromArray,
    WithTitle,
    WithHeadings,
    ShouldAutoSize,
    WithStyles
{
    public function __construct(private $breakdown) {}

    public function headings(): array
    {
        return [
            'Order Status',
            'Count',
        ];
    }

    public function array(): array
    {
        return [
            ['Completed', $this->breakdown['completed'] ?? 0],
            ['Pending', $this->breakdown['pending'] ?? 0],
            ['Confirmed', $this->breakdown['confirmed'] ?? 0],
            ['Ready', $this->breakdown['ready'] ?? 0],
            ['Delivering', $this->breakdown['delivering'] ?? 0],
            ['Cancelled', $this->breakdown['cancelled'] ?? 0],
            ['Refunded', $this->breakdown['refunded'] ?? 0],
            ['Total Orders', $this->breakdown['total'] ?? 0],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],

            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => 'F06292'],
            ],
        ]);

        // Body style
        $sheet->getStyle('A2:B9')->applyFromArray([
            'font' => [
                'size' => 11,
            ],
        ]);

        // Borders
        $sheet->getStyle('A1:B9')->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => 'thin',
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ]);

        // Total row highlight
        $sheet->getStyle('A9:B9')->applyFromArray([
            'font' => [
                'bold' => true,
            ],

            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => 'FCE7F3'],
            ],
        ]);

        return [];
    }

    public function title(): string
    {
        return 'Order Status';
    }
}