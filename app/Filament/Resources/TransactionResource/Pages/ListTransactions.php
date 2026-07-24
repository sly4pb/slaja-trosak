<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\TransactionResource\Widgets\ExpensesByMonthChart;
use App\Filament\Resources\TransactionResource\Widgets\ExpensesByTypeChart;
use App\Filament\Resources\TransactionResource\Widgets\ExpensesVsIncomeChart;
use App\Filament\Resources\TransactionResource\Widgets\ExpensesByCategoryChart;
use App\Filament\Resources\TransactionResource\Widgets\TransactionStatsWidget;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    public ?string $filterFrom  = null;
    public ?string $filterUntil = null;

    protected function getHeaderActions(): array
    {
        if (auth()->user()?->isAdmin() ?? false) {
            return [];
        }

        return [
            CreateAction::make()->label('Add Transaction'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionStatsWidget::class,
            ExpensesVsIncomeChart::class,
            ExpensesByTypeChart::class,
            ExpensesByMonthChart::class,
            ExpensesByCategoryChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return [
            'default' => 1, // 1 column on mobile
            'sm'      => 1,
            'md'      => 2,
            'lg'      => 3, // 3 columns on desktop
            'xl'      => 3,
        ];
    }

    public function applyTableFilters(): void
    {
        parent::applyTableFilters();

        $filters = $this->tableFilters ?? [];
        $from    = $filters['date_range']['from']  ?? null;
        $until   = $filters['date_range']['until'] ?? null;

        $this->filterFrom  = $from  ?: null;
        $this->filterUntil = $until ?: null;

        $this->dispatch('filtersUpdated', from: $this->filterFrom, until: $this->filterUntil);
    }

    public function getTabs(): array
    {
        return [
            'all'      => Tab::make('All'),
            'expenses' => Tab::make('Expenses')
                             ->modifyQueryUsing(fn($query) => $query->where('amount', '<', 0)),
            'income'   => Tab::make('Incomes')
                             ->modifyQueryUsing(fn($query) => $query->where('amount', '>', 0)),
        ];
    }
}