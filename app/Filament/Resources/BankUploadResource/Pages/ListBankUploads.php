<?php

namespace App\Filament\Resources\BankUploadResource\Pages;

use App\Filament\Resources\BankUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBankUploads extends ListRecords
{
    protected static string $resource = BankUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New upload'),
        ];
    }
}
