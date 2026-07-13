<?php

namespace App\Filament\Resources\TukarJadwals;

use App\Filament\Resources\TukarJadwals\Pages\CreateTukarJadwal;
use App\Filament\Resources\TukarJadwals\Pages\EditTukarJadwal;
use App\Filament\Resources\TukarJadwals\Pages\ListTukarJadwals;
use App\Filament\Resources\TukarJadwals\Pages\ViewTukarJadwal;
use App\Filament\Resources\TukarJadwals\Schemas\TukarJadwalForm;
use App\Filament\Resources\TukarJadwals\Schemas\TukarJadwalInfolist;
use App\Filament\Resources\TukarJadwals\Tables\TukarJadwalsTable;
use App\Models\TukarJadwal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TukarJadwalResource extends Resource
{
    protected static ?string $model = TukarJadwal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Pengajuan';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return TukarJadwalForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TukarJadwalInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TukarJadwalsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTukarJadwals::route('/'),
            'create' => CreateTukarJadwal::route('/create'),
            'view' => ViewTukarJadwal::route('/{record}'),
            'edit' => EditTukarJadwal::route('/{record}/edit'),
        ];
    }
}
