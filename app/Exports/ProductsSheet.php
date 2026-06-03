<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsSheet implements
    FromArray,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithTitle
{
    public function __construct(private $products) {}

    public function array(): array
    {
        return collect($this->products)->map(function ($item) {

            return [
                $item['rank'],
                $item['name'],
                $item['qty'],
                '$' . number_format($item['revenue'], 2),
            ];

        })->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | TABLE HEADERS
    |--------------------------------------------------------------------------
    */

    public function headings(): array
    {
        return [
            'Rank',
            'Product Name',
            'Qty Sold',
            'Revenue',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SHEET NAME
    |--------------------------------------------------------------------------
    */

    public function title(): string
    {
        return 'Top Products';
    }

    /*
    |--------------------------------------------------------------------------
    | STYLING
    |--------------------------------------------------------------------------
    */

    public function styles(Worksheet $sheet)
    {
        // Header row style
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],

            'fill' => [
                'fillType' => 'solid',
                'startColor' => [
                    'rgb' => 'F06292',
                ],
            ],
        ]);

        // Center rank column
        $sheet->getStyle('A:A')->getAlignment()->setHorizontal('center');

        // Currency column bold
        $sheet->getStyle('D:D')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
        ]);

        return [];
    }
}