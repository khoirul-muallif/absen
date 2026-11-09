<?php

namespace App\Filament\Resources\Informasis;

use App\Filament\Resources\Informasis\Pages\CreateInformasi;
use App\Filament\Resources\Informasis\Pages\EditInformasi;
use App\Filament\Resources\Informasis\Pages\ListInformasis;
use App\Filament\Resources\Informasis\Schemas\InformasiForm;
use App\Filament\Resources\Informasis\Tables\InformasisTable;
use App\Models\Informasi;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InformasiResource extends Resource
{
    protected static ?string $model = Informasi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Master Data';

    protected static ?string $recordTitleAttribute = 'judul';

    public static function getModelLabel(): string
    {
        return 'Informasi';
    }

    /**
     * Tanpa ini Filament menampilkan "Informasis" di menu, judul halaman,
     * dan tombol — "informasi" sudah jamak sendiri di bahasa Indonesia.
     */
    public static function getPluralModelLabel(): string
    {
        return 'Informasi';
    }

    public static function getNavigationLabel(): string
    {
        return 'Informasi';
    }

    public static function form(Schema $schema): Schema
    {
        return InformasiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InformasisTable::configure($table);
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
            'index' => ListInformasis::route('/'),
            'create' => CreateInformasi::route('/create'),
            'edit' => EditInformasi::route('/{record}/edit'),
        ];
    }
}
