<?php

namespace App\Filament\Admin\Resources\KategoriResource\Pages;

use App\Filament\Admin\Resources\KategoriResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKategoris extends ListRecords
{
    protected static string $resource = KategoriResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
