<?php

namespace App\Filament\Resources\CategoryRuleResource\Pages;

use App\Filament\Resources\CategoryRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditCategoryRule extends EditRecord
{
    protected static string $resource = CategoryRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}