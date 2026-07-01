<?php

namespace App\Filament\Resources\QrInstansis\Pages;

use App\Filament\Resources\QrInstansis\QrInstansiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQrInstansis extends ListRecords
{
    protected static string $resource = QrInstansiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
