<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\DompetResource\Pages;
use App\Filament\Admin\Resources\DompetResource\RelationManagers;
use App\Models\Dompet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DompetResource extends Resource
{
    protected static ?string $model = Dompet::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('pengguna_id')
                    ->relationship('pengguna', 'name')
                    ->required(),
                Forms\Components\TextInput::make('nama_dompet')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('jenis_dompet')
                    ->required(),
                Forms\Components\TextInput::make('nomor_akun')
                    ->maxLength(100)
                    ->default(null),
                Forms\Components\TextInput::make('saldo_awal')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('mata_uang')
                    ->required()
                    ->maxLength(3)
                    ->default('IDR'),
                Forms\Components\TextInput::make('ikon')
                    ->maxLength(100)
                    ->default(null),
                Forms\Components\TextInput::make('warna')
                    ->maxLength(20)
                    ->default(null),
                Forms\Components\TextInput::make('urutan')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('aktif')
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
                Tables\Columns\TextColumn::make('nama_dompet')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jenis_dompet'),
                Tables\Columns\TextColumn::make('nomor_akun')
                    ->searchable(),
                Tables\Columns\TextColumn::make('saldo_awal')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mata_uang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ikon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warna')
                    ->searchable(),
                Tables\Columns\TextColumn::make('urutan')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('aktif')
                    ->boolean(),
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
            'index' => Pages\ListDompets::route('/'),
            'create' => Pages\CreateDompet::route('/create'),
            'edit' => Pages\EditDompet::route('/{record}/edit'),
        ];
    }
}
