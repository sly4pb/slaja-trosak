<?php

namespace App\Filament\Resources\TransactionResource\Widgets;

use App\Enums\TransactionCategory;
use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class ExpensesByCategoryChart extends ChartWidget
{
    protected ?string $heading = 'Expenses by Category';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

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
        return 'bar';
    }

    protected function getData(): array
    {
        $isAdmin = auth()->user()?->isAdmin() ?? false;

        $data = Transaction::query()
                           ->when(! $isAdmin, fn ($q) => $q->where('user_id', auth()->id()))
                           ->when($this->filterFrom,  fn ($q) => $q->whereDate('date', '>=', $this->filterFrom))
                           ->when($this->filterUntil, fn ($q) => $q->whereDate('date', '<=', $this->filterUntil))
                           ->where('amount', '<', 0)
                           ->selectRaw('category, SUM(ABS(amount)) as total')
                           ->groupBy('category')
                           ->orderByDesc('total')
                           ->get();

        $labels = $data->map(function ($row) {
            if ($row->category === null) {
                return 'Unsorted';
            }
            return $row->category instanceof TransactionCategory
                ? $row->category->label()
                : (TransactionCategory::tryFrom($row->category)?->label() ?? 'Unsorted');
        })->toArray();

        $totals = $data->map(fn ($row) => round($row->total, 2))->toArray();

        $colors = $data->map(function ($row) {
            if ($row->category === null) {
                return '#cbd5e1';
            }
            $cat = $row->category instanceof TransactionCategory
                ? $row->category
                : TransactionCategory::tryFrom($row->category);
            return $this->categoryColor($cat);
        })->toArray();

        return [
            'datasets' => [
                [
                    'label'           => 'Expenses (RSD)',
                    'data'            => $totals,
                    'backgroundColor' => $colors,
                    'borderColor'     => $colors,
                    'borderWidth'     => 1,
                    'borderRadius'    => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'font'        => ['size' => 11],
                        'maxRotation' => 30,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => ['font' => ['size' => 11]],
                ],
            ],
        ];
    }

    private function categoryColor(?TransactionCategory $category): string
    {
        if ($category === null) {
            return '#cbd5e1';
        }

        return match($category) {
            TransactionCategory::HouseBills     => '#f59e0b',
            TransactionCategory::Food           => '#22c55e',
            TransactionCategory::Pharmacy       => '#ef4444',
            TransactionCategory::Car            => '#3b82f6',
            TransactionCategory::Clothes        => '#a855f7',
            TransactionCategory::Restaurants    => '#ec4899',
            TransactionCategory::Gym            => '#06b6d4',
            TransactionCategory::Theater        => '#8b5cf6',
            TransactionCategory::Gifts          => '#f43f5e',
            TransactionCategory::Travel         => '#6366f1',
            TransactionCategory::HouseholdItems => '#84cc16',
            TransactionCategory::Other          => '#94a3b8',
        };
    }
}