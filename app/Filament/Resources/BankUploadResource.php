<?php

namespace App\Filament\Resources;

use App\Enums\BankType;
use App\Filament\Resources\BankUploadResource\Pages;
use App\Models\BankUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;

class BankUploadResource extends Resource
{
    protected static ?string $model = BankUpload::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationLabel = 'Upload file';
    protected static ?string $modelLabel = 'Upload';
    protected static ?string $pluralModelLabel = 'Uploads';
    protected static ?int $navigationSort = 1;

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
            Section::make('New file import')
                   ->schema([
                       Select::make('bank')
                             ->label('Bank')
                             ->options(BankType::options())
                             ->required()
                             ->native(false)
                             ->placeholder('Choose bank'),

                       FileUpload::make('file')
                                 ->label('File')
                                 ->required()
                                 ->acceptedFileTypes([
                                     'application/vnd.ms-excel',
                                     'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                     'text/csv',
                                     'application/csv',
                                 ])
                                 ->maxSize(10240)
                                 ->helperText('Supported formats: .xls, .xlsx, .csv'),
                   ])
                   ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                BankUpload::query()
                    ->where('user_id', auth()->id())
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('bank')
                    ->label('Bank')
                    ->formatStateUsing(fn (BankType $state) => $state->label())
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->limit(40),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match($state) {
                        'done'       => 'success',
                        'processing' => 'warning',
                        'failed'     => 'danger',
                        default      => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'done'       => 'Done',
                        'processing' => 'Processing',
                        'failed'     => 'Failed',
                        default      => 'Pending',
                    }),

                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bank')
                    ->label('Bank')
                    ->options(BankType::options()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'    => 'Pending',
                        'processing' => 'Processing',
                        'done'       => 'Done',
                        'failed'     => 'Failed',
                    ]),
            ])
            ->recordActions([
                Action::make('view_transactions')
                    ->label('View Transactions')
                    ->icon('heroicon-o-eye')
                    ->url(fn (BankUpload $record) => TransactionResource::getUrl('index', [
                        'tableFilters[bank_upload_id][value]' => $record->id,
                    ]))
                    ->visible(fn (BankUpload $record) => $record->isDone()),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->action(fn (BankUpload $record) => $record->delete()),
            ])
            ->emptyStateHeading('No uploaded statements')
            ->emptyStateDescription('Click "New Upload" to import a statement from the bank.')
            ->emptyStateIcon('heroicon-o-arrow-up-tray');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBankUploads::route('/'),
            'create' => Pages\CreateBankUpload::route('/create'),
        ];
    }
}
