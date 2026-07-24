<?php

namespace App\Filament\Admin\Resources\AnggaranBulananResource\Pages;

use App\Filament\Admin\Resources\AnggaranBulananResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnggaranBulanan extends EditRecord
{
    protected static string $resource = AnggaranBulananResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
