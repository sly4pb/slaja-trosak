<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;

class ExpensesByTypeChart extends ChartWidget
{
    protected ?string $heading = 'Expenses by Type of Transaction';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        $data = Transaction::query()
                           ->when(! $isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
                           ->where('amount', '<', 0)
                           ->selectRaw('type, SUM(ABS(amount)) as total')
                           ->groupBy('type')
                           ->orderByDesc('total')
                           ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Expenses (RSD)',
                    'data'  => $data->pluck('total')->map(fn ($v) => round($v, 2))->toArray(),
                    'backgroundColor' => [
                        '#f87171', '#fb923c', '#fbbf24', '#a3e635',
                        '#34d399', '#22d3ee', '#60a5fa', '#a78bfa',
                        '#f472b6', '#94a3b8',
                    ],
                ],
            ],
            'labels' => $data->pluck('type')->map(fn ($t) => $t ?: 'Other')->toArray(),
        ];
    }
}
