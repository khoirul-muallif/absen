<?php

namespace App\Filament\Resources\JenisCutis\Pages;

use App\Filament\Resources\JenisCutis\JenisCutiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJenisCuti extends EditRecord
{
    protected static string $resource = JenisCutiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
