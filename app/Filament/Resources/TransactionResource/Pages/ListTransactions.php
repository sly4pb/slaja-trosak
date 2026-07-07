<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\TransactionResource\Widgets\ExpensesByMonthChart;
use App\Filament\Resources\TransactionResource\Widgets\ExpensesByTypeChart;
use App\Filament\Resources\TransactionResource\Widgets\ExpensesVsIncomeChart;
use App\Filament\Resources\TransactionResource\Widgets\ExpensesByCategoryChart;
use App\Models\Transaction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        if (auth()->user()?->isAdmin() ?? false) {
            return [];
        }

        return [
            CreateAction::make()
                        ->label('Add Transaction'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExpensesVsIncomeChart::class,
            ExpensesByTypeChart::class,
            ExpensesByMonthChart::class,
            ExpensesByCategoryChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 3;
    }

    public function getTabs(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;
        $userId  = auth()->id();

        $scoped = fn (Builder $query) => $isAdmin
            ? $query
            : $query->where('user_id', $userId);

        return [
            'all' => Tab::make('All')
                        ->badge(
                            $scoped(Transaction::query())->count()
                        ),

            'expenses' => Tab::make('Expenses')
                             ->modifyQueryUsing(fn (Builder $q) => $q->where('amount', '<', 0))
                             ->badge(
                                 $scoped(Transaction::query())->where('amount', '<', 0)->count()
                             )
                             ->badgeColor('danger'),

            'income' => Tab::make('Incomes')
                           ->modifyQueryUsing(fn (Builder $q) => $q->where('amount', '>', 0))
                           ->badge(
                               $scoped(Transaction::query())->where('amount', '>', 0)->count()
                           )
                           ->badgeColor('success'),
        ];
    }
}