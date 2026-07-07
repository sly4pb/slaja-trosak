<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected static ?string $title = 'Add Transaction';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id']          = auth()->id();
        $data['bank_upload_id']   = null; // rucno uneta transakcija, nije iz uploada
        $data['currency']         = $data['currency'] ?? 'RSD';
        $data['transaction_date'] = $data['date'];

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
