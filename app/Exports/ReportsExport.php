<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\StatsSheet;
use App\Exports\OrdersSheet;
use App\Exports\ProductsSheet;
use App\Exports\BreakdownSheet;
use App\Exports\ChartSheet;

class ReportsExport implements WithMultipleSheets
{
    public function __construct(
        private $stats,
        //private $orders,
        private $products,
        private $breakdown,
        private $chart
    ) {}

    public function sheets(): array
    {
        return [
            new StatsSheet($this->stats),
            //new OrdersSheet($this->orders),
            new ProductsSheet($this->products),
            new BreakdownSheet($this->breakdown),
            new ChartSheet($this->chart),
        ];
    }
}