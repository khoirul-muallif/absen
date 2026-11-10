<?php

namespace App\Filament\Resources\Karyawans\Pages;

use App\Exports\KaryawanTemplateExport;
use App\Filament\Resources\Karyawans\KaryawanResource;
use App\Models\Karyawan;
use App\Models\PolaRotasi;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListKaryawans extends ListRecords
{
    protected static string $resource = KaryawanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('unduhTemplateImport')
                ->label('Unduh Template Import')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $unit = collect()
                        ->merge(Karyawan::query()->whereNotNull('unit_kerja')->distinct()->pluck('unit_kerja'))
                        ->merge(PolaRotasi::query()->whereNotNull('unit_kerja')->distinct()->pluck('unit_kerja'))
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                    return Excel::download(
                        new KaryawanTemplateExport($unit),
                        'template-import-karyawan-' . now()->format('Y-m-d') . '.xlsx',
                    );
                }),
        ];
    }
}
