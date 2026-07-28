<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TransferDompetResource\Pages;
use App\Filament\Admin\Resources\TransferDompetResource\RelationManagers;
use App\Models\TransferDompet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransferDompetResource extends Resource
{
    protected static ?string $model = TransferDompet::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('pengguna_id')
                    ->relationship('pengguna', 'name')
                    ->required(),
                Forms\Components\Select::make('dompet_asal_id')
                    ->relationship('dompetAsal', 'id')
                    ->required(),
                Forms\Components\Select::make('dompet_tujuan_id')
                    ->relationship('dompetTujuan', 'id')
                    ->required(),
                Forms\Components\TextInput::make('kode_transfer')
                    ->required()
                    ->maxLength(50),
                Forms\Components\DateTimePicker::make('tanggal_transfer')
                    ->required(),
                Forms\Components\TextInput::make('nominal')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('biaya_admin')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('bukti_transfer')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('sumber_pencatatan')
                    ->required(),
                Forms\Components\DateTimePicker::make('diselesaikan_pada'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('pengguna.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dompetAsal.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dompetTujuan.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kode_transfer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tanggal_transfer')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nominal')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('biaya_admin')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bukti_transfer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('sumber_pencatatan'),
                Tables\Columns\TextColumn::make('diselesaikan_pada')
                    ->dateTime()
                    ->sortable(),
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
            'index' => Pages\ListTransferDompets::route('/'),
            'create' => Pages\CreateTransferDompet::route('/create'),
            'edit' => Pages\EditTransferDompet::route('/{record}/edit'),
        ];
    }
}
