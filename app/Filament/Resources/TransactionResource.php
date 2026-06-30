<?php

namespace App\Filament\Resources;

use App\Enums\BankType;
use App\Enums\TransactionCategory;
use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Transactions';
    protected static ?string $modelLabel = 'Transaction';
    protected static ?string $pluralModelLabel = 'Transactions';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                           ->when(
                        ! (auth()->user()?->isAdmin() ?? false),
                        fn ($query) => $query->where('user_id', auth()->id())
                    )
                           ->latest('date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                                         ->label('User')
                                         ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                                         ->badge()
                                         ->color('gray'),

                Tables\Columns\TextColumn::make('date')
                                         ->label('Date')
                                         ->date('d.m.Y')
                                         ->sortable(),

                Tables\Columns\TextColumn::make('bank')
                                         ->label('Bank')
                                         ->formatStateUsing(fn (BankType $state) => $state->label())
                                         ->badge()
                                         ->color('primary'),

                Tables\Columns\TextColumn::make('type')
                                         ->label('Type')
                                         ->badge()
                                         ->color('gray')
                                         ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                                         ->label('Category')
                                         ->badge()
                                         ->color(fn (?TransactionCategory $state) => $state?->color() ?? 'gray')
                                         ->formatStateUsing(fn (?TransactionCategory $state) => $state?->label() ?? '—'),

                Tables\Columns\TextColumn::make('description')
                                         ->label('Description')
                                         ->limit(50)
                                         ->tooltip(fn (Transaction $record) => $record->description)
                                         ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                                         ->label('Amount')
                                         ->formatStateUsing(function (Transaction $record): string {
                                             $formatted = number_format(abs($record->amount), 2, ',', '.');
                                             $sign      = $record->amount >= 0 ? '+' : '-';
                                             return "{$sign} {$formatted} {$record->currency}";
                                         })
                                         ->color(fn (Transaction $record) => $record->amount >= 0 ? 'success' : 'danger')
                                         ->alignEnd()
                                         ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                                         ->label('Currency')
                                         ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                                           ->label('User')
                                           ->relationship('user', 'name')
                                           ->visible(fn () => auth()->user()?->isAdmin() ?? false),

                Tables\Filters\SelectFilter::make('bank')
                                           ->label('Bank')
                                           ->options(BankType::options()),

                Tables\Filters\SelectFilter::make('category')
                                           ->label('Category')
                                           ->options(TransactionCategory::options()),

                Tables\Filters\SelectFilter::make('type')
                                           ->label('Type')
                                           ->options(
                                               fn () => Transaction::query()
                                                                   ->when(
                                                                       ! (auth()->user()?->isAdmin() ?? false),
                                                                       fn ($q) => $q->where('user_id', auth()->id())
                                                                   )
                                                                   ->whereNotNull('type')
                                                                   ->distinct()
                                                                   ->orderBy('type')
                                                                   ->pluck('type', 'type')
                                                                   ->toArray()
                                           ),

                Tables\Filters\Filter::make('date_range')
                                     ->label('Period')
                                     ->form([
                                         DatePicker::make('from')
                                                   ->label('From')
                                                   ->native(false),
                                         DatePicker::make('until')
                                                   ->label('Until')
                                                   ->native(false),
                                     ])
                                     ->query(function ($query, array $data) {
                                         return $query
                                             ->when($data['from'],  fn ($q) => $q->whereDate('date', '>=', $data['from']))
                                             ->when($data['until'], fn ($q) => $q->whereDate('date', '<=', $data['until']));
                                     })
                                     ->indicateUsing(function (array $data): array {
                                         $indicators = [];

                                         if ($data['from'] ?? null) {
                                             $indicators['from'] = 'From: ' . \Carbon\Carbon::parse($data['from'])->format('d.m.Y');
                                         }

                                         if ($data['until'] ?? null) {
                                             $indicators['until'] = 'Until: ' . \Carbon\Carbon::parse($data['until'])->format('d.m.Y');
                                         }

                                         return $indicators;
                                     }),

                Tables\Filters\SelectFilter::make('bank_upload_id')
                                           ->label('Upload file')
                                           ->relationship(
                                               'bankUpload',
                                               'original_filename',
                                               fn ($query) => $query->when(
                                                   ! (auth()->user()?->isAdmin() ?? false),
                                                   fn ($q) => $q->where('user_id', auth()->id())
                                               )
                                           ),

                Tables\Filters\Filter::make('expenses')
                                     ->label('Only expenses')
                                     ->query(fn ($query) => $query->where('amount', '<', 0))
                                     ->toggle(),

                Tables\Filters\Filter::make('income')
                                     ->label('Only income')
                                     ->query(fn ($query) => $query->where('amount', '>', 0))
                                     ->toggle(),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('details')
                      ->label('Details')
                      ->icon('heroicon-o-information-circle')
                      ->modalHeading('Transaction Details')
                      ->modalContent(fn (Transaction $record) => view(
                          'filament.modals.transaction-details',
                          ['transaction' => $record]
                      ))
                      ->modalSubmitAction(false)
                      ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([
                BulkAction::make('assign_category')
                          ->label('Assign Category')
                          ->icon('heroicon-o-tag')
                          ->schema([
                              Select::make('category')
                                    ->label('Category')
                                    ->options(TransactionCategory::options())
                                    ->required()
                                    ->native(false),
                          ])
                          ->action(function (Collection $records, array $data) {
                              $records->each(
                                  fn (Transaction $record) => $record->update(['category' => $data['category']])
                              );

                              Notification::make()
                                          ->success()
                                          ->title('Category assigned')
                                          ->body(count($records) . ' transaction(s) updated.')
                                          ->send();
                          })
                          ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('date', 'desc')
            ->striped()
            ->emptyStateHeading('No transactions')
            ->emptyStateDescription('Upload a statement from the bank to see transactions.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}
