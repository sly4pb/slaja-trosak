<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;

class ExpensesVsIncomeChart extends ChartWidget
{
    protected ?string $heading = 'Expenses vs Income';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;
        $userId  = auth()->id();

        $expenses = (float) Transaction::query()
                                       ->when(! $isAdmin, fn ($q) => $q->where('user_id', $userId))
                                       ->where('amount', '<', 0)
                                       ->sum('amount');

        $income = (float) Transaction::query()
                                     ->when(! $isAdmin, fn ($q) => $q->where('user_id', $userId))
                                     ->where('amount', '>', 0)
                                     ->sum('amount');

        return [
            'datasets' => [
                [
                    'label' => 'RSD',
                    'data'  => [round(abs($expenses), 2), round($income, 2)],
                    'backgroundColor' => ['#f87171', '#34d399'],
                ],
            ],
            'labels' => ['Expenses', 'Income'],
        ];
    }
}
