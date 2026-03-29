<?php

namespace App\Filament\Imports;

use App\Models\InventoryItem;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;

class InventoryItemImporter extends Importer
{
    protected static ?string $model = InventoryItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id')
                ->integer()
                ->rules(['nullable', 'integer'])
                ->ignoreBlankState(),
            ImportColumn::make('shop_id')
                ->integer()
                ->requiredMappingForNewRecordsOnly()
                ->rules(['nullable', 'integer', 'exists:shops,id']),
            ImportColumn::make('name')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('category')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('timing')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('assigned_staff_id')
                ->integer()
                ->rules(['nullable', 'integer', 'exists:staff,id']),
            ImportColumn::make('unit')
                ->rules(['nullable', 'string', 'max:50']),
            ImportColumn::make('input_type')
                ->rules(['nullable', 'string', 'in:number,text,select']),
            ImportColumn::make('options')
                ->rules(['nullable', 'string'])
                ->castStateUsing(function (mixed $state): ?array {
                    if ($state === null || $state === '') {
                        return null;
                    }
                    if (is_array($state)) {
                        return $state;
                    }
                    $decoded = json_decode((string) $state, true);

                    return is_array($decoded) ? $decoded : null;
                }),
            ImportColumn::make('is_active')
                ->rules(['nullable'])
                ->castStateUsing(function (mixed $originalState, mixed $state): bool {
                    if ($state === null || $state === '') {
                        return true;
                    }
                    if (is_bool($state)) {
                        return $state;
                    }
                    $s = strtolower(trim((string) $state));

                    return in_array($s, ['1', 'true', 'yes', 'on'], true);
                }),
        ];
    }

    public function resolveRecord(): ?InventoryItem
    {
        $id = $this->data['id'] ?? null;

        if ($id === null || $id === '') {
            return new InventoryItem;
        }

        return InventoryItem::query()->find($id) ?? new InventoryItem;
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
                    'category' => ['required', 'string', 'max:255'],
                    'assigned_staff_id' => ['required', 'integer', 'exists:staff,id'],
                    'unit' => ['required', 'string', 'max:50'],
                ],
            );
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Import completed. '.Number::format($import->successful_rows).' rows.';
    }
}
