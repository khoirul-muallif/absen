<?php

namespace App\Filament\Resources\QrInstansis\Pages;

use App\Filament\Resources\QrInstansis\QrInstansiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQrInstansi extends EditRecord
{
    protected static string $resource = QrInstansiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
