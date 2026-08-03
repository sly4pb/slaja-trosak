<?php

namespace App\Filament\User\Resources\CategoryRuleResource\Pages;

use App\Filament\User\Resources\CategoryRuleResource;
use Filament\Resources\Pages\EditRecord;

class EditCategoryRule extends EditRecord
{
    protected static string $resource = CategoryRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}