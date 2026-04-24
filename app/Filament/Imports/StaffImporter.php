<?php

namespace App\Filament\Imports;

use App\Filament\Concerns\RunsFilamentCsvJobsOnSyncQueueInLocal;
use App\Models\Staff;
use App\Support\FixedShiftsCsv;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;

class StaffImporter extends Importer
{
    use RunsFilamentCsvJobsOnSyncQueueInLocal;

    protected static ?string $model = Staff::class;

    public static function getColumns(): array
    {
        $columns = [
            ImportColumn::make('id')
                ->label('id')
                ->integer()
                ->rules(['nullable', 'integer'])
                ->ignoreBlankState(),
            ImportColumn::make('shop_id')
                ->label('shop_id')
                ->integer()
                ->requiredMappingForNewRecordsOnly()
                ->rules(['nullable', 'integer', 'exists:shops,id']),
            ImportColumn::make('name')
                ->label('name')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('pin_code')
                ->label('pin_code')
                ->rules(['nullable', 'string', 'digits:4'])
                ->ignoreBlankState(),
            ImportColumn::make('role')
                ->label('role')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('target_weekly_hours')
                ->label('target_weekly_hours')
                ->integer()
                ->rules(['nullable', 'integer'])
                ->ignoreBlankState(),
            ImportColumn::make('wage')
                ->label('wage')
                ->numeric()
                ->rules(['nullable', 'numeric'])
                ->ignoreBlankState(),
            ImportColumn::make('job_level_id')
                ->label('job_level_id')
                ->integer()
                ->rules(['nullable', 'integer', 'exists:job_levels,id'])
                ->ignoreBlankState(),
            ImportColumn::make('age')
                ->label('age')
                ->integer()
                ->rules(['nullable', 'integer'])
                ->ignoreBlankState(),
            ImportColumn::make('gender')
                ->label('gender')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('origin')
                ->label('origin')
                ->rules(['nullable', 'string', 'max:255'])
                ->ignoreBlankState(),
            ImportColumn::make('note')
                ->label('note')
                ->rules(['nullable', 'string'])
                ->ignoreBlankState(),
            ImportColumn::make('extra_profile')
                ->label('extra_profile')
                ->rules(['nullable'])
                ->ignoreBlankState()
                ->castStateUsing(function (mixed $originalState, mixed $state): ?array {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    if (is_array($state)) {
                        return $state;
                    }

                    if (! is_string($state)) {
                        return null;
                    }

                    $decoded = json_decode($state, true);

                    return is_array($decoded) ? $decoded : null;
                }),
            ImportColumn::make('is_active')
                ->label('is_active')
                ->rules(['nullable'])
                ->castStateUsing(function (mixed $originalState, mixed $state): ?bool {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    if (is_bool($state)) {
                        return $state;
                    }

                    $s = strtolower(trim((string) $state));

                    return match ($s) {
                        '1', 'true', 'yes', 'on' => true,
                        '0', 'false', 'no', 'off', '' => false,
                        default => null,
                    };
                }),
        ];

        foreach (FixedShiftsCsv::flatColumnNames() as $col) {
            $columns[] = ImportColumn::make($col)
                ->label($col)
                ->rules(['nullable', 'string'])
                ->ignoreBlankState()
                ->fillRecordUsing(fn () => null);
        }

        return $columns;
    }

    public function resolveRecord(): ?Staff
    {
        $keyName = app(Staff::class)->getKeyName();
        $keyColumnName = $this->columnMap[$keyName] ?? $keyName;

        $id = $this->data[$keyColumnName] ?? null;

        if ($id === null || $id === '') {
            return new Staff;
        }

        $found = Staff::query()->find($id);

        if ($found) {
            return $found;
        }

        // CSV に id 列があるが DB にまだ無い → 新規作成（エクスポートした CSV をそのまま戻す場合など）
        return new Staff;
    }

    public function validateData(): void
    {
        parent::validateData();

        if (! $this->record?->exists) {
            Validator::validate(
                $this->data,
                [
                    'shop_id' => ['required', 'integer', 'exists:shops,id'],
                    'name' => ['required', 'string', 'max:255'],
                ],
                [],
                [
                    'shop_id' => 'shop_id',
                    'name' => 'name',
                ],
            );
        }
    }

    protected function beforeFill(): void
    {
        $anyShiftColumnPresent = false;

        foreach (FixedShiftsCsv::flatColumnNames() as $col) {
            if (array_key_exists($col, $this->data)) {
                $anyShiftColumnPresent = true;

                break;
            }
        }

        if (! $anyShiftColumnPresent) {
            return;
        }

        $flat = [];

        foreach (FixedShiftsCsv::flatColumnNames() as $col) {
            $flat[$col] = $this->data[$col] ?? null;
        }

        $this->record->setAttribute('fixed_shifts', FixedShiftsCsv::expand($flat));
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your staff import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
