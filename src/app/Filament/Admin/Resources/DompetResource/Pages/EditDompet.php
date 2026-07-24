<?php

namespace App\Filament\Admin\Resources\DompetResource\Pages;

use App\Filament\Admin\Resources\DompetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDompet extends EditRecord
{
    protected static string $resource = DompetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
