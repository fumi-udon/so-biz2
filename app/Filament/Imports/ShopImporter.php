<?php

namespace App\Filament\Imports;

use App\Filament\Concerns\RunsFilamentCsvJobsOnSyncQueueInLocal;
use App\Models\Shop;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;

class ShopImporter extends Importer
{
    use RunsFilamentCsvJobsOnSyncQueueInLocal;

    protected static ?string $model = Shop::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->label('id')
                ->integer()
                ->rules(['nullable', 'integer'])
                ->ignoreBlankState(),
            ImportColumn::make('name')
                ->label('name')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('is_active')
                ->label('is_active')
                ->rules(['nullable'])
                ->ignoreBlankState()
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
    }

    public function resolveRecord(): ?Shop
    {
        $keyName = app(Shop::class)->getKeyName();
        $keyColumnName = $this->columnMap[$keyName] ?? $keyName;

        $id = $this->data[$keyColumnName] ?? null;

        if ($id === null || $id === '') {
            return new Shop;
        }

        $found = Shop::query()->find($id);

        if ($found) {
            return $found;
        }

        return new Shop;
    }

    public function validateData(): void
    {
        parent::validateData();

        if (! $this->record?->exists) {
            Validator::validate(
                $this->data,
                [
                    'name' => ['required', 'string', 'max:255'],
                ],
                [],
                [
                    'name' => 'name',
                ],
            );
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your shop import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
