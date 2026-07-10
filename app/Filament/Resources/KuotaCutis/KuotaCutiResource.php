<?php

namespace App\Filament\Resources\KuotaCutis;

use App\Filament\Resources\KuotaCutis\Pages\CreateKuotaCuti;
use App\Filament\Resources\KuotaCutis\Pages\EditKuotaCuti;
use App\Filament\Resources\KuotaCutis\Pages\ListKuotaCutis;
use App\Filament\Resources\KuotaCutis\Schemas\KuotaCutiForm;
use App\Filament\Resources\KuotaCutis\Tables\KuotaCutisTable;
use App\Models\KuotaCuti;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KuotaCutiResource extends Resource
{
    protected static ?string $model = KuotaCuti::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return KuotaCutiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KuotaCutisTable::configure($table);
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
            'index' => ListKuotaCutis::route('/'),
            'create' => CreateKuotaCuti::route('/create'),
            'edit' => EditKuotaCuti::route('/{record}/edit'),
        ];
    }
}
