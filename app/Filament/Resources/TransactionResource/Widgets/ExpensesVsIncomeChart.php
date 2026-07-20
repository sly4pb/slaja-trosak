<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class ExpensesVsIncomeChart extends ChartWidget
{
    protected ?string $heading = 'Expenses vs Income';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public ?string $filterFrom  = null;
    public ?string $filterUntil = null;

    #[On('filtersUpdated')]
    public function updateFilters(?string $from = null, ?string $until = null): void
    {
        $this->filterFrom  = $from;
        $this->filterUntil = $until;
        $this->updateChartData();
    }

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;
        $userId  = auth()->id();

        $base = Transaction::query()
                           ->when(! $isAdmin, fn ($q) => $q->where('user_id', $userId))
                           ->when($this->filterFrom,  fn ($q) => $q->whereDate('date', '>=', $this->filterFrom))
                           ->when($this->filterUntil, fn ($q) => $q->whereDate('date', '<=', $this->filterUntil));

        $expenses = (float) (clone $base)->where('amount', '<', 0)->sum('amount');
        $income   = (float) (clone $base)->where('amount', '>', 0)->sum('amount');

        return [
            'datasets' => [
                [
                    'label'           => 'RSD',
                    'data'            => [round(abs($expenses), 2), round($income, 2)],
                    'backgroundColor' => ['#f87171', '#34d399'],
                ],
            ],
            'labels' => ['Expenses', 'Income'],
        ];
    }
}