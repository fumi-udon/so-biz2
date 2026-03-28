<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Exports\StaffExporter;
use App\Filament\Imports\StaffImporter;
use App\Filament\Resources\Staff\StaffResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\ImportAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    public ?string $staffExportDownloadUrl = null;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(StaffImporter::class),
            ExportAction::make()
                ->exporter(StaffExporter::class)
                ->after(function (ListStaff $livewire): void {
                    $export = Export::query()
                        ->where('user_id', Auth::id())
                        ->whereNotNull('completed_at')
                        ->where('exporter', StaffExporter::class)
                        ->latest('id')
                        ->first();

                    if (! $export) {
                        return;
                    }

                    $livewire->staffExportDownloadUrl = URL::signedRoute(
                        'filament.exports.download',
                        [
                            'authGuard' => Filament::getAuthGuard(),
                            'export' => $export,
                            'format' => ExportFormat::Csv,
                        ],
                        absolute: true,
                    );

                    $livewire->replaceMountedAction('staffExportDownload');
                }),
            Action::make('staffExportDownload')
                ->label('CSVダウンロード')
                ->hidden()
                ->modalHeading('エクスポート完了')
                ->modalDescription('CSVの準備ができました。下のボタンから保存してください。')
                ->modalWidth(Width::Medium)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('閉じる')
                ->modalContent(fn (ListStaff $livewire): HtmlString => new HtmlString(
                    '<div class="fi-sc  fi-sc-w-1/1">'
                    .'<a'
                    .' href="'.e($livewire->staffExportDownloadUrl).'"'
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
