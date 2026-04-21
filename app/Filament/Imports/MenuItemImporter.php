<?php

namespace App\Filament\Imports;

use App\Models\DietaryBadge;
use App\Models\MenuItem;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;

class MenuItemImporter extends Importer
{
    protected static ?string $model = MenuItem::class;

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
            ImportColumn::make('menu_category_id')
                ->integer()
                ->requiredMappingForNewRecordsOnly()
                ->rules(['nullable', 'integer', 'exists:menu_categories,id']),
            ImportColumn::make('name')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('kitchen_name')
                ->rules(['nullable', 'string', 'max:255'])
                ->castStateUsing(function (mixed $state): ?string {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    $s = trim((string) $state);

                    return $s === '' ? null : $s;
                }),
            ImportColumn::make('slug')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('description')
                ->rules(['nullable', 'string']),
            ImportColumn::make('hero_image_path')
                ->rules(['nullable', 'string', 'max:2048']),
            ImportColumn::make('from_price_minor')
                ->integer()
                ->rules(['nullable', 'integer', 'min:0']),
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
            ImportColumn::make('allergy_note')
                ->rules(['nullable', 'string']),
            ImportColumn::make('dietary_slugs')
                ->rules(['nullable', 'string', 'max:2000'])
                ->fillRecordUsing(function (): void {
                    // モデル属性には載せず saveRecord() で pivot 同期
                }),
            ImportColumn::make('options_payload')
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
        ];
    }

    public function resolveRecord(): ?MenuItem
    {
        $id = $this->data['id'] ?? null;
        if ($id === null || $id === '') {
            return new MenuItem;
        }

        return MenuItem::query()->find($id) ?? new MenuItem;
    }

    public function saveRecord(): void
    {
        $this->record->hero_image_disk = $this->record->hero_image_disk ?: 'public';
        $this->record->save();

        $raw = $this->data['dietary_slugs'] ?? '';
        if (! is_string($raw) || trim($raw) === '') {
            $this->record->dietaryBadges()->detach();

            return;
        }

        $slugs = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($slugs === []) {
            $this->record->dietaryBadges()->detach();

            return;
        }

        $badges = DietaryBadge::query()->whereIn('slug', $slugs)->orderBy('sort_order')->get();
        $sync = [];
        foreach ($badges as $i => $badge) {
            $sync[$badge->id] = ['sort_order' => $i];
        }
        $this->record->dietaryBadges()->sync($sync);
    }

    public function validateData(): void
    {
        parent::validateData();

        if (! $this->record?->exists) {
            Validator::validate(
                $this->data,
                [
                    'shop_id' => ['required', 'integer', 'exists:shops,id'],
                    'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
                    'name' => ['required', 'string', 'max:255'],
                    'slug' => ['nullable', 'string', 'max:255'],
                    'from_price_minor' => ['required', 'integer', 'min:0'],
                ],
            );
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Import completed. '.Number::format($import->successful_rows).' rows.';
    }
}
