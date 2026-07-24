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

        $storedFilename = basename($data['file']);
        $originalName   = preg_replace('/^\d+_/', '', $storedFilename);

        $absolutePath = Storage::disk('private')->path($data['file']);
        $file         = new \Illuminate\Http\File($absolutePath);

        $uploaded = new \Illuminate\Http\UploadedFile(
            $file->getPathname(),
            $originalName,
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

        if ($record->isFailed()) {
            return Notification::make()
                               ->danger()
                               ->title('Import error')
                               ->body($record->error_message);
        }

        if ($record->isDone()) {
            $hasDuplicates = str_contains($record->error_message ?? '', 'duplicate');

            $body = $record->transactions_count > 0
                ? "Imported {$record->transactions_count} transaction(s)."
                : 'No new transactions found.';

            if ($hasDuplicates) {
                preg_match('/Skipped (\d+)/', $record->error_message ?? '', $matches);
                $skipped = $matches[1] ?? '?';
                $body   .= " {$skipped} duplicate(s) skipped.";
            }

            return Notification::make()
                               ->success()
                               ->title('Statement successfully imported')
                               ->body($body);
        }

        return Notification::make()
                           ->warning()
                           ->title('File uploaded')
                           ->body('Processing...');
    }
}