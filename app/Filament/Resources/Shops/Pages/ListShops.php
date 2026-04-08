<?php

namespace App\Filament\Resources\Shops\Pages;

use App\Filament\Exports\ShopExporter;
use App\Filament\Imports\ShopImporter;
use App\Filament\Resources\Shops\ShopResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\ImportAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class ListShops extends ListRecords
{
    protected static string $resource = ShopResource::class;

    public ?string $shopExportDownloadUrl = null;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(ShopImporter::class),
            ExportAction::make()
                ->exporter(ShopExporter::class)
                ->after(function (ListShops $livewire): void {
                    $export = Export::query()
                        ->where('user_id', Auth::id())
                        ->whereNotNull('completed_at')
                        ->where('exporter', ShopExporter::class)
                        ->latest('id')
                        ->first();

                    if (! $export) {
                        return;
                    }

                    $livewire->shopExportDownloadUrl = URL::signedRoute(
                        'filament.exports.download',
                        [
                            'authGuard' => Filament::getAuthGuard(),
                            'export' => $export,
                            'format' => ExportFormat::Csv,
                        ],
                        absolute: true,
                    );

                    $livewire->replaceMountedAction('shopExportDownload');
                }),
            Action::make('shopExportDownload')
                ->label('CSVダウンロード')
                ->hidden()
                ->modalHeading('エクスポート完了')
                ->modalDescription('CSVの準備ができました。下のボタンから保存してください。')
                ->modalWidth(MaxWidth::Medium)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('閉じる')
                ->modalContent(fn (ListShops $livewire): HtmlString => new HtmlString(
                    '<div class="fi-sc  fi-sc-w-1/1">'
                    .'<a'
                    .' href="'.e($livewire->shopExportDownloadUrl).'"'
                    .' class="fi-btn fi-btn-size-md fi-color-primary fi-btn-color-primary inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm outline-none transition duration-75 focus-visible:ring-2" '
                    .' target="_blank" rel="noopener noreferrer"'
                    .'>'
                    .'CSVをダウンロード'
                    .'</a>'
                    .'</div>'
                )),
            CreateAction::make(),
        ];
    }
}
