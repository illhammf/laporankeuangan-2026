<?php

namespace App\Filament\Admin\Resources\DompetResource\Pages;

use App\Filament\Admin\Resources\DompetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDompets extends ListRecords
{
    protected static string $resource = DompetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
