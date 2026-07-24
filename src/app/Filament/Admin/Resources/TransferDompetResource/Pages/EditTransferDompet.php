<?php

namespace App\Filament\Admin\Resources\TransferDompetResource\Pages;

use App\Filament\Admin\Resources\TransferDompetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransferDompet extends EditRecord
{
    protected static string $resource = TransferDompetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
