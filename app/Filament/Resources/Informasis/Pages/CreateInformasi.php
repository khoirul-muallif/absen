<?php

namespace App\Filament\Resources\Informasis\Pages;

use App\Filament\Resources\Informasis\InformasiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInformasi extends CreateRecord
{
    protected static string $resource = InformasiResource::class;
        /**
     * created_by diisi dari admin yang sedang login, bukan field form —
     * supaya tidak bisa mengaku-aku sebagai admin lain.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
