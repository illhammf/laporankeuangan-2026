<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AnggaranBulananResource\Pages;
use App\Filament\Admin\Resources\AnggaranBulananResource\RelationManagers;
use App\Models\AnggaranBulanan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AnggaranBulananResource extends Resource
{
    protected static ?string $model = AnggaranBulanan::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('pengguna_id')
                    ->relationship('pengguna', 'name')
                    ->required(),
                Forms\Components\Select::make('kategori_id')
                    ->relationship('kategori', 'id')
                    ->required(),
                Forms\Components\TextInput::make('bulan')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('tahun')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('nominal_anggaran')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('batas_peringatan')
                    ->required()
                    ->numeric()
                    ->default(80.00),
                Forms\Components\Toggle::make('ulangi_bulan_berikutnya')
                    ->required(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pengguna.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kategori.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bulan')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tahun')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nominal_anggaran')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('batas_peringatan')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('ulangi_bulan_berikutnya')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListAnggaranBulanans::route('/'),
            'create' => Pages\CreateAnggaranBulanan::route('/create'),
            'edit' => Pages\EditAnggaranBulanan::route('/{record}/edit'),
        ];
    }
}
