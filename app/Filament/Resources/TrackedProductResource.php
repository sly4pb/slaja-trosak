<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrackedProductResource\Pages;
use App\Models\TrackedProduct;
use App\Services\PriceCheckService;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class TrackedProductResource extends Resource
{
    protected static ?string $model = TrackedProduct::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Price Tracker';
    protected static ?string $modelLabel = 'Tracked Product';
    protected static ?string $pluralModelLabel = 'Tracked Products';
    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('url')
                     ->label('Product URL')
                     ->url()
                     ->required()
                     ->maxLength(2048)
                     ->placeholder('https://www.example.com/product-page')
                     ->helperText('Paste a product page URL. We\'ll check the price every 6 hours.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                TrackedProduct::query()
                              ->where('user_id', auth()->id())
                              ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                                         ->label('Product')
                                         ->limit(50)
                                         ->formatStateUsing(fn (?string $state, TrackedProduct $record) => $state ?: $record->url)
                                         ->url(fn (TrackedProduct $record) => $record->url, shouldOpenInNewTab: true)
                                         ->searchable(),

                Tables\Columns\TextColumn::make('current_price')
                                         ->label('Price')
                                         ->formatStateUsing(fn (?string $state, TrackedProduct $record) =>
                                             $state !== null
                                                 ? number_format((float) $state, 2, ',', '.') . ' ' . $record->currency
                                                 : '—'
                                         )
                                         ->alignEnd(),

                Tables\Columns\TextColumn::make('status')
                                         ->label('Status')
                                         ->badge()
                                         ->color(fn (string $state) => match($state) {
                                             'ok'      => 'success',
                                             'failed'  => 'danger',
                                             default   => 'gray',
                                         })
                                         ->formatStateUsing(fn (string $state) => match($state) {
                                             'ok'      => 'OK',
                                             'failed'  => 'Failed',
                                             default   => 'Pending',
                                         })
                                         ->tooltip(fn (TrackedProduct $record) => $record->error_message),

                Tables\Columns\TextColumn::make('last_checked_at')
                                         ->label('Last Checked')
                                         ->dateTime('d.m.Y H:i')
                                         ->placeholder('Never')
                                         ->sortable(),
            ])
            ->recordActions([
                Action::make('check_now')
                      ->label('Check Now')
                      ->icon('heroicon-o-arrow-path')
                      ->color('gray')
                      ->action(function (TrackedProduct $record) {
                          $service = app(PriceCheckService::class);
                          $result  = $service->check($record);

                          if ($record->fresh()->isOk()) {
                              Notification::make()
                                  ->success()
                                  ->title('Price checked')
                                  ->body($result['changed']
                                      ? 'Price changed! Check your email.'
                                      : 'Price unchanged.')
                                  ->send();
                          } else {
                              Notification::make()
                                  ->danger()
                                  ->title('Check failed')
                                  ->body($record->fresh()->error_message)
                                  ->send();
                          }
                      }),

                DeleteAction::make()
                                           ->label('Delete'),
            ])
            ->emptyStateHeading('No tracked products')
            ->emptyStateDescription('Add a product URL to start tracking its price.')
            ->emptyStateIcon('heroicon-o-tag');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTrackedProducts::route('/'),
            'create' => Pages\CreateTrackedProduct::route('/create'),
        ];
    }
}
