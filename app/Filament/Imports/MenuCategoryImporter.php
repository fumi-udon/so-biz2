<?php

namespace App\Filament\Imports;

use App\Models\MenuCategory;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;

class MenuCategoryImporter extends Importer
{
    protected static ?string $model = MenuCategory::class;

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
            ImportColumn::make('slug')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('sort_order')
                ->integer()
                ->rules(['nullable', 'integer', 'min:0']),
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

    public function resolveRecord(): ?MenuCategory
    {
        $id = $this->data['id'] ?? null;
        if ($id === null || $id === '') {
            return new MenuCategory;
        }

        return MenuCategory::query()->find($id) ?? new MenuCategory;
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
                    'slug' => ['required', 'string', 'max:255'],
                ],
            );
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Import completed. '.Number::format($import->successful_rows).' rows.';
    }
}
