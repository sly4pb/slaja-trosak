<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class TransactionStatsWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public ?string $filterFrom  = null;
    public ?string $filterUntil = null;

    #[On('filtersUpdated')]
    public function updateFilters(?string $from = null, ?string $until = null): void
    {
        $this->filterFrom  = $from;
        $this->filterUntil = $until;
    }

    protected function getStats(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;
        $userId  = auth()->id();

        $base = Transaction::query()
                           ->when(! $isAdmin, fn ($q) => $q->where('user_id', $userId))
                           ->when($this->filterFrom,  fn ($q) => $q->whereDate('date', '>=', $this->filterFrom))
                           ->when($this->filterUntil, fn ($q) => $q->whereDate('date', '<=', $this->filterUntil));

        $total    = (clone $base)->count();
        $expenses = (clone $base)->where('amount', '<', 0)->count();
        $income   = (clone $base)->where('amount', '>', 0)->count();

        $totalAmount    = (float) (clone $base)->sum('amount');
        $expensesAmount = (float) (clone $base)->where('amount', '<', 0)->sum('amount');
        $incomeAmount   = (float) (clone $base)->where('amount', '>', 0)->sum('amount');

        $fmt = fn (float $v) => number_format(abs($v), 2, ',', '.') . ' RSD';

        return [
            Stat::make('All Transactions', $total)
                ->description($fmt($totalAmount))
                ->descriptionIcon($totalAmount >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($totalAmount >= 0 ? 'success' : 'danger')
                ->chart([]),

            Stat::make('Expenses', $expenses)
                ->description($fmt($expensesAmount))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Income', $income)
                ->description($fmt($incomeAmount))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}