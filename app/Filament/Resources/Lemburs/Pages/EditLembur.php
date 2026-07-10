<?php

namespace App\Filament\Resources\Lemburs\Pages;

use App\Filament\Resources\Lemburs\LemburResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLembur extends EditRecord
{
    protected static string $resource = LemburResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
