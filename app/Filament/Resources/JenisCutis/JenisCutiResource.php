<?php

namespace App\Filament\Resources\JenisCutis;

use App\Filament\Resources\JenisCutis\Pages\CreateJenisCuti;
use App\Filament\Resources\JenisCutis\Pages\EditJenisCuti;
use App\Filament\Resources\JenisCutis\Pages\ListJenisCutis;
use App\Filament\Resources\JenisCutis\Schemas\JenisCutiForm;
use App\Filament\Resources\JenisCutis\Tables\JenisCutisTable;
use App\Models\JenisCuti;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class JenisCutiResource extends Resource
{
    protected static ?string $model = JenisCuti::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'nama';

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return JenisCutiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JenisCutisTable::configure($table);
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
            'index' => ListJenisCutis::route('/'),
            'create' => CreateJenisCuti::route('/create'),
            'edit' => EditJenisCuti::route('/{record}/edit'),
        ];
    }
}
