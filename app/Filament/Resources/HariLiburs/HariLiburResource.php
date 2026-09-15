<?php

namespace App\Filament\Resources\HariLiburs;

use App\Filament\Resources\HariLiburs\Pages\CreateHariLibur;
use App\Filament\Resources\HariLiburs\Pages\EditHariLibur;
use App\Filament\Resources\HariLiburs\Pages\ListHariLiburs;
use App\Filament\Resources\HariLiburs\Pages\ViewHariLibur;
use App\Filament\Resources\HariLiburs\Schemas\HariLiburForm;
use App\Filament\Resources\HariLiburs\Schemas\HariLiburInfolist;
use App\Filament\Resources\HariLiburs\Tables\HariLibursTable;
use App\Models\HariLibur;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HariLiburResource extends Resource
{
    protected static ?string $model = HariLibur::class;

    // Sebelumnya OutlinedRectangleStack — persis sama dengan JadwalResource,
    // jadi dua menu berbeda di grup yang sama tampil dengan ikon identik.
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    // Label default Filament memluralkan "HariLibur" jadi "Hari Liburs".
    protected static ?string $navigationLabel = 'Hari Libur';

    protected static ?string $pluralLabel = 'Hari Libur';

    protected static ?string $label = 'Hari Libur';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static string|UnitEnum|null $navigationGroup = 'Presensi';

    // Absensi = 1, Jadwal = 2. Sebelumnya kosong, jadi urutannya di menu
    // tidak pasti relatif terhadap dua Resource itu.
    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return HariLiburForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HariLiburInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HariLibursTable::configure($table);
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
            'index' => ListHariLiburs::route('/'),
            'create' => CreateHariLibur::route('/create'),
            'view' => ViewHariLibur::route('/{record}'),
            'edit' => EditHariLibur::route('/{record}/edit'),
        ];
    }
}
