<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersSheet implements
    FromArray,
    WithTitle,
    WithHeadings,
    ShouldAutoSize,
    WithStyles
{
    public function __construct(private $orders) {}

    public function headings(): array
    {
        return [
            'Order Number',
            'Status',
            'Total ($)',
            'Date',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {

            $rows[] = [
                $order->order_number,
                ucfirst($order->status),
                $order->total,
                $order->created_at->format('Y-m-d'),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->orders) + 1;

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("A1:D1")->applyFromArray([
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

        $sheet->getStyle("A2:D{$lastRow}")->applyFromArray([
            'font' => [
                'size' => 11,
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | BORDERS
        |--------------------------------------------------------------------------
        */

        $sheet->getStyle("A1:D{$lastRow}")->applyFromArray([
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

        $sheet->getStyle("C2:C{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('"$"#,##0.00');

        /*
        |--------------------------------------------------------------------------
        | STATUS COLORS
        |--------------------------------------------------------------------------
        */

        for ($i = 2; $i <= $lastRow; $i++) {

            $status = strtolower($sheet->getCell("B{$i}")->getValue());

            $color = 'E2E8F0';

            if ($status === 'completed') {
                $color = 'DCFCE7';
            } elseif ($status === 'pending') {
                $color = 'FEF3C7';
            } elseif ($status === 'confirmed') {
                $color = 'DBEAFE';
            } elseif ($status === 'ready') {
                $color = 'F3E8FF';
            } elseif ($status === 'delivering') {
                $color = 'CFFAFE';
            } elseif ($status === 'cancelled') {
                $color = 'E2E8F0';
            } elseif ($status === 'refunded') {
                $color = 'FCE7F3';
            }

            $sheet->getStyle("B{$i}")->applyFromArray([
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => $color],
                ],
            ]);
        }

        return [];
    }

    public function title(): string
    {
        return 'Orders';
    }
}