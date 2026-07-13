<?php

namespace App\Filament\Resources;

use App\Enums\TransactionCategory;
use App\Filament\Resources\CategoryRuleResource\Pages;
use App\Models\CategoryRule;
use App\Models\Transaction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;

class CategoryRuleResource extends Resource
{
    protected static ?string $model = CategoryRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Category Rules';
    protected static ?string $modelLabel = 'Rule';
    protected static ?string $pluralModelLabel = 'Category Rules';
    protected static ?int $navigationSort = 3;

    // Only regular users see their own rules — admins don't categorize
    public static function shouldRegisterNavigation(): bool
    {
        return ! (auth()->user()?->isAdmin() ?? false);
    }

    public static function canAccess(): bool
    {
        return ! (auth()->user()?->isAdmin() ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('keyword')
                     ->label('Keyword')
                     ->required()
                     ->maxLength(255)
                     ->helperText('Transactions whose description contains this keyword will be auto-categorized.'),

            Select::make('category')
                  ->label('Category')
                  ->options(TransactionCategory::options())
                  ->required()
                  ->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                CategoryRule::query()
                            ->where('user_id', auth()->id())
                            ->orderBy('keyword')
            )
            ->columns([
                Tables\Columns\TextColumn::make('keyword')
                                         ->label('Keyword')
                                         ->searchable()
                                         ->limit(60),

                Tables\Columns\TextColumn::make('category')
                                         ->label('Category')
                                         ->badge()
                                         ->formatStateUsing(fn (TransactionCategory $state) => $state->label())
                                         ->color(fn (TransactionCategory $state) => $state->color()),

                Tables\Columns\TextColumn::make('created_at')
                                         ->label('Created')
                                         ->date('d.m.Y')
                                         ->sortable()
                                         ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),

                // Delete with optional transaction reset
                Action::make('delete')
                                     ->label('Delete')
                                     ->icon('heroicon-o-trash')
                                     ->color('danger')
                                     ->requiresConfirmation()
                                     ->modalHeading('Delete Rule')
                                     ->modalDescription(fn (CategoryRule $record) => "Delete rule for keyword: \"{$record->keyword}\"?")
                                     ->form([
                                         Select::make('reset_transactions')
                                               ->label('Reset matching transactions?')
                                               ->options([
                                                   '0' => 'No — keep existing categories on transactions',
                                                   '1' => 'Yes — reset to no category',
                                               ])
                                               ->default('0')
                                               ->native(false)
                                               ->required(),
                                     ])
                                     ->action(function (CategoryRule $record, array $data) {
                                         $keyword  = $record->keyword;
                                         $category = $record->category->value;
                                         $record->delete();

                                         $resetCount = 0;
                                         if ($data['reset_transactions'] === '1') {
                                             $resetCount = Transaction::where('user_id', auth()->id())
                                                                      ->where('category', $category)
                                                                      ->where('description', 'LIKE', '%' . $keyword . '%')
                                                                      ->update(['category' => null]);
                                         }

                                         $body = 'Rule deleted.';
                                         if ($resetCount > 0) {
                                             $body .= " {$resetCount} transaction(s) reset to no category.";
                                         }

                                         Notification::make()
                                                     ->success()
                                                     ->title('Rule deleted')
                                                     ->body($body)
                                                     ->send();
                                     }),
            ])
            ->emptyStateHeading('No rules yet')
            ->emptyStateDescription('Rules are created automatically when you assign a category to transactions.')
            ->emptyStateIcon('heroicon-o-adjustments-horizontal');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategoryRules::route('/'),
            'edit'  => Pages\EditCategoryRule::route('/{record}/edit'),
        ];
    }
}