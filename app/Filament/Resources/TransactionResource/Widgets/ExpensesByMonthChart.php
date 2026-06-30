<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;

class ExpensesByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Expenses by Month';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'pie';
    }

    private const MONTHS = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    protected function getData(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        $data = Transaction::query()
                           ->when(! $isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
                           ->where('amount', '<', 0)
                           ->selectRaw('MONTH(date) as month, YEAR(date) as year, SUM(ABS(amount)) as total')
                           ->groupBy('year', 'month')
                           ->orderBy('year')
                           ->orderBy('month')
                           ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Expenses (RSD)',
                    'data'  => $data->pluck('total')->map(fn ($v) => round($v, 2))->toArray(),
                    'backgroundColor' => [
                        '#f87171', '#fb923c', '#fbbf24', '#a3e635',
                        '#34d399', '#22d3ee', '#60a5fa', '#a78bfa',
                        '#f472b6', '#94a3b8', '#fcd34d', '#86efac',
                    ],
                ],
            ],
            'labels' => $data->map(fn ($row) => self::MONTHS[(int) $row->month] . ' ' . $row->year)->toArray(),
        ];
    }
}
