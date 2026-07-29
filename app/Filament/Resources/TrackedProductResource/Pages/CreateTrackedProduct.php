<?php

namespace App\Filament\Resources\TrackedProductResource\Pages;

use App\Filament\Resources\TrackedProductResource;
use App\Models\TrackedProduct;
use App\Services\PriceCheckService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateTrackedProduct extends CreateRecord
{
    protected static string $resource = TrackedProductResource::class;

    protected static ?string $title = 'Track a New Product';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Check the price immediately so the user doesn't wait up to 6 hours
        $service = app(PriceCheckService::class);
        $service->check($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        $record = $this->record->fresh();

        if ($record->isOk()) {
            return Notification::make()
                ->success()
                ->title('Product added')
                ->body("Current price: " . number_format((float) $record->current_price, 2, ',', '.') . " {$record->currency}");
        }

        return Notification::make()
            ->warning()
            ->title('Product added, but price check failed')
            ->body($record->error_message . ' — you can retry with "Check Now".');
    }
}
