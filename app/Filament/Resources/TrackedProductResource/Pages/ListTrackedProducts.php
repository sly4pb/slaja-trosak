<?php

namespace App\Filament\Resources\TrackedProductResource\Pages;

use App\Filament\Resources\TrackedProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrackedProducts extends ListRecords
{
    protected static string $resource = TrackedProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Product'),
        ];
    }
}
