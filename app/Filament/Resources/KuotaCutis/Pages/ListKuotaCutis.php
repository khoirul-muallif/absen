<?php

namespace App\Filament\Resources\KuotaCutis\Pages;

use App\Filament\Resources\KuotaCutis\KuotaCutiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKuotaCutis extends ListRecords
{
    protected static string $resource = KuotaCutiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
