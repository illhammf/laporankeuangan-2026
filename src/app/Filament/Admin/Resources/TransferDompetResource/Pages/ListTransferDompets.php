<?php

namespace App\Filament\Admin\Resources\TransferDompetResource\Pages;

use App\Filament\Admin\Resources\TransferDompetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransferDompets extends ListRecords
{
    protected static string $resource = TransferDompetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
