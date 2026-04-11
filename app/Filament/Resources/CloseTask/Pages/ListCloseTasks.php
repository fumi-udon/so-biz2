<?php

namespace App\Filament\Resources\CloseTask\Pages;

use App\Filament\Resources\CloseTaskResource;
use App\Models\CloseTask;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ListCloseTasks extends ListRecords
{
    protected static string $resource = CloseTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('export')
                ->label('CSVエクスポート')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => auth()->user()->can('view_any_close::task'))
                ->action(function (): StreamedResponse {
                    return response()->streamDownload(function (): void {
                        $handle = fopen('php://temp', 'r+');
                        if ($handle === false) {
                            throw new \RuntimeException('一時バッファを開けませんでした。');
                        }

                        fwrite($handle, "\xEF\xBB\xBF");
                        fputcsv($handle, ['id', 'title', 'description', 'is_active']);

                        foreach (CloseTask::all()->sortBy('id') as $task) {
                            fputcsv($handle, [
                                $task->id,
                                $task->title,
                                $task->description ?? '',
                                $task->is_active ? '1' : '0',
                            ]);
                        }

                        rewind($handle);
                        fpassthru($handle);
                        fclose($handle);
                    }, 'close_tasks_'.now()->format('Ymd').'.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
            Action::make('import')
                ->label('CSVインポート')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('クローズチェック項目のCSVインポート')
                ->modalSubmitActionLabel('インポートする')
                ->form([
                    FileUpload::make('file')
                        ->label('CSVファイル')
                        ->required()
                        ->disk('local')
                        ->directory('close_tasks_imports')
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                            'application/csv',
                            'text/comma-separated-values',
                        ])
                        ->maxSize(1024),
                ])
                ->visible(fn (): bool => auth()->user()->can('create_close::task') && auth()->user()->can('update_close::task'))
                ->action(function (array $data): void {
                    $relativePath = $data['file'] ?? null;
                    if (is_array($relativePath)) {
                        $relativePath = $relativePath[0] ?? null;
                    }
                    if (! is_string($relativePath) || $relativePath === '') {
                        Notification::make()
                            ->danger()
                            ->title('インポート失敗')
                            ->body('ファイルが選択されていません。')
                            ->send();

                        return;
                    }

                    $fullPath = Storage::disk('local')->path($relativePath);
                    if (! is_readable($fullPath)) {
                        Notification::make()
                            ->danger()
                            ->title('インポート失敗')
                            ->body('ファイルを読み取れませんでした。')
                            ->send();

                        return;
                    }

                    try {
                        $count = DB::transaction(function () use ($fullPath): int {
                            $utf8 = $this->normalizeUploadedCsvToUtf8($fullPath);

                            $handle = fopen('php://temp', 'r+');
                            if ($handle === false) {
                                throw new \RuntimeException('一時バッファを開けませんでした。');
                            }

                            try {
                                fwrite($handle, $utf8);
                                rewind($handle);

                                $header = fgetcsv($handle);
                                if ($header === false) {
                                    throw new \RuntimeException('CSVが空です。');
                                }

                                $processed = 0;

                                while (($row = fgetcsv($handle)) !== false) {
                                    if ($this->isCsvRowEmpty($row)) {
                                        continue;
                                    }

                                    $idRaw = isset($row[0]) ? trim((string) $row[0]) : '';
                                    $title = isset($row[1]) ? trim((string) $row[1]) : '';
                                    $description = isset($row[2]) ? (string) $row[2] : '';
                                    $isActiveRaw = $row[3] ?? '1';

                                    if ($title === '') {
                                        continue;
                                    }

                                    $isActive = $this->parseCsvBoolean($isActiveRaw);

                                    if ($idRaw !== '' && ctype_digit($idRaw)) {
                                        $record = CloseTask::query()->find((int) $idRaw);
                                        if ($record !== null) {
                                            $record->update([
                                                'title' => $title,
                                                'description' => $description !== '' ? $description : null,
                                                'is_active' => $isActive,
                                            ]);
                                            $processed++;

                                            continue;
                                        }
                                    }

                                    CloseTask::query()->create([
                                        'title' => $title,
                                        'description' => $description !== '' ? $description : null,
                                        'is_active' => $isActive,
                                        'image_path' => null,
                                    ]);
                                    $processed++;
                                }

                                return $processed;
                            } finally {
                                fclose($handle);
                            }
                        });

                        Storage::disk('local')->delete($relativePath);

                        Notification::make()
                            ->success()
                            ->title('インポート完了')
                            ->body("{$count} 件を取り込みました。")
                            ->send();
                    } catch (Throwable $e) {
                        if (isset($relativePath) && is_string($relativePath) && $relativePath !== '') {
                            Storage::disk('local')->delete($relativePath);
                        }

                        Notification::make()
                            ->danger()
                            ->title('インポート失敗')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
        ];
    }

    /**
     * アップロード CSV をバイナリで読み、UTF-8 に正規化する（Shift-JIS / CP932 等の Excel 出力を想定）。
     */
    private function normalizeUploadedCsvToUtf8(string $fullPath): string
    {
        $raw = file_get_contents($fullPath);
        if ($raw === false) {
            throw new \RuntimeException('CSVファイルを読み取れませんでした。');
        }

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if ($raw === '') {
            return '';
        }

        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        $candidates = ['UTF-8', 'SJIS-win', 'SJIS', 'CP51932', 'Windows-1252', 'ISO-8859-1'];
        $from = mb_detect_encoding($raw, $candidates, true);
        if ($from === false) {
            $from = 'SJIS-win';
        }

        $converted = mb_convert_encoding($raw, 'UTF-8', $from);
        if ($converted === false) {
            throw new \RuntimeException('文字コードの変換に失敗しました。');
        }

        return $converted;
    }

    /**
     * @param  array<int, string|null>|false  $row
     */
    protected function isCsvRowEmpty(array|false $row): bool
    {
        if ($row === false) {
            return true;
        }

        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function parseCsvBoolean(mixed $value): bool
    {
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }
}
