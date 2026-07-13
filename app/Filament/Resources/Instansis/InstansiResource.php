<?php

namespace App\Filament\Resources\Instansis;

use App\Filament\Resources\Instansis\Pages\CreateInstansi;
use App\Filament\Resources\Instansis\Pages\EditInstansi;
use App\Filament\Resources\Instansis\Pages\ListInstansis;
use App\Filament\Resources\Instansis\Schemas\InstansiForm;
use App\Filament\Resources\Instansis\Tables\InstansisTable;
use App\Models\Instansi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InstansiResource extends Resource
{
    protected static ?string $model = Instansi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $navigationLabel = 'Instansi';

    protected static ?string $pluralLabel = 'Data Instansi';

    protected static ?string $label = 'Instansi';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return InstansiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstansisTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListInstansis::route('/'),
            'create' => CreateInstansi::route('/create'),
            'edit'   => EditInstansi::route('/{record}/edit'),
        ];
    }
}
