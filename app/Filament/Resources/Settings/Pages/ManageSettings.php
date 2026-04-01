<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;

class ManageSettings extends ManageRecords
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getFooter(): ?View
    {
        if (! view()->exists('filament.resources.settings.pages.settings-key-mapping-footer')) {
            return null;
        }

        return view('filament.resources.settings.pages.settings-key-mapping-footer');
    }
}
