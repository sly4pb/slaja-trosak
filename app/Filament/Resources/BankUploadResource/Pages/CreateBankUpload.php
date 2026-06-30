<?php

namespace App\Filament\Resources\BankUploadResource\Pages;

use App\Enums\BankType;
use App\Filament\Resources\BankUploadResource;
use App\Services\BankUploadService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateBankUpload extends CreateRecord
{
    protected static string $resource = BankUploadResource::class;

    protected static ?string $title = 'Statement upload';

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $bank    = BankType::from($data['bank']);
        $service = app(BankUploadService::class);

        $tempPath = Storage::disk('private')->path($data['file']);
        $file     = new \Illuminate\Http\File($tempPath);
        $uploaded = new \Illuminate\Http\UploadedFile(
            $file->getPathname(),
            $file->getFilename(),
            $file->getMimeType(),
            null,
            true
        );

        $upload = $service->handle($uploaded, $bank, auth()->id());

        Storage::disk('private')->delete($data['file']);

        return $upload;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        $record = $this->record;

        if ($record->isDone()) {
            return Notification::make()
                ->success()
                ->title('Izvod uspješno uvezen')
                ->body("Pronađeno {$record->transactions_count} transakcija.");
        }

        if ($record->isFailed()) {
            return Notification::make()
                ->danger()
                ->title('Greška pri uvozu')
                ->body($record->error_message);
        }

        return Notification::make()
            ->warning()
            ->title('Fajl je uploadovan')
            ->body('Obrada je u toku...');
    }
}
