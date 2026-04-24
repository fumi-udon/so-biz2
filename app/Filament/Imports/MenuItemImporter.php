<?php

namespace App\Filament\Imports;

use App\Filament\Concerns\RunsFilamentCsvJobsOnSyncQueueInLocal;
use App\Models\DietaryBadge;
use App\Models\MenuItem;
use App\Support\MenuItemMoney;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class MenuItemImporter extends Importer
{
    use RunsFilamentCsvJobsOnSyncQueueInLocal;

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
                ->rules(['nullable'])
                ->castStateUsing(function (mixed $state): ?array {
                    // C-2: 不正 JSON・構造崩れは RowImportFailedException（行失敗）。空セルは null のまま（従来どおりクリア可）。
                    if ($state === null || $state === '') {
                        return null;
                    }

                    if (is_array($state)) {
                        $decoded = $state;
                    } else {
                        $raw = trim((string) $state);
                        if ($raw === '') {
                            return null;
                        }
                        $decoded = json_decode($raw, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new RowImportFailedException(
                                'options_payload の JSON が不正です（JSON パースに失敗）。'
                            );
                        }
                        if ($decoded === null && strtolower($raw) === 'null') {
                            return null;
                        }
                    }

                    if (! is_array($decoded)) {
                        throw new RowImportFailedException(
                            'options_payload の JSON が不正です（JSON パースに失敗、または配列形式ではありません）。'
                        );
                    }

                    if (array_key_exists('rules', $decoded) && ! is_array($decoded['rules'])) {
                        throw new RowImportFailedException(
                            'options_payload.rules はオブジェクト（連想配列）である必要があります。'
                        );
                    }
                    if (array_key_exists('styles', $decoded) && ! is_array($decoded['styles'])) {
                        throw new RowImportFailedException(
                            'options_payload.styles は配列である必要があります。'
                        );
                    }
                    if (array_key_exists('toppings', $decoded) && ! is_array($decoded['toppings'])) {
                        throw new RowImportFailedException(
                            'options_payload.toppings は配列である必要があります。'
                        );
                    }

                    $rulesIn = is_array($decoded['rules'] ?? null) ? $decoded['rules'] : [];
                    if (array_key_exists('style_required', $rulesIn)) {
                        $sr = $rulesIn['style_required'];
                        $allowed = is_bool($sr)
                            || is_int($sr)
                            || (is_string($sr) && in_array(strtolower(trim($sr)), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true));
                        if (! $allowed) {
                            throw new RowImportFailedException(
                                'options_payload の rules.style_required は true / false（または 0 / 1）である必要があります。'
                            );
                        }
                    }
                    $styleRequired = false;
                    if (array_key_exists('style_required', $rulesIn)) {
                        $sr = $rulesIn['style_required'];
                        if (is_bool($sr)) {
                            $styleRequired = $sr;
                        } elseif (is_int($sr)) {
                            $styleRequired = $sr !== 0;
                        } else {
                            $styleRequired = in_array(strtolower(trim((string) $sr)), ['1', 'true', 'yes', 'on'], true);
                        }
                    }

                    $makeUnique = static function (string $base, array &$used): string {
                        $candidate = $base;
                        if (! isset($used[$candidate])) {
                            $used[$candidate] = true;

                            return $candidate;
                        }
                        $n = 2;
                        while (true) {
                            $candidate = $base.'-'.$n;
                            if (! isset($used[$candidate])) {
                                $used[$candidate] = true;

                                return $candidate;
                            }
                            $n++;
                        }
                    };

                    $usedStyleIds = [];
                    $styles = [];
                    foreach ($decoded['styles'] ?? [] as $i => $style) {
                        if (! is_array($style)) {
                            throw new RowImportFailedException(
                                sprintf('options_payload.styles[%d] は配列である必要があります。', $i)
                            );
                        }
                        $name = trim((string) ($style['name'] ?? ''));
                        $id = trim((string) ($style['id'] ?? ''));
                        if ($id === '') {
                            $id = Str::slug($name) ?: 'style-'.($i + 1);
                        }
                        $id = $makeUnique($id, $usedStyleIds);
                        $pv = $style['price_minor'] ?? 0;
                        if ($pv === null || $pv === '') {
                            $pv = 0;
                        }
                        if (is_string($pv)) {
                            $pv = trim($pv);
                            if ($pv === '') {
                                $pv = 0;
                            }
                        }
                        if (is_bool($pv) || is_array($pv)) {
                            throw new RowImportFailedException(
                                sprintf(
                                    'options_payload.styles[%d].price_minor は 0 以上の整数である必要があります（入力値: %s）。',
                                    $i,
                                    var_export($pv, true)
                                )
                            );
                        }
                        if (! is_numeric($pv)) {
                            throw new RowImportFailedException(
                                sprintf(
                                    'options_payload.styles[%d].price_minor は 0 以上の整数である必要があります（入力値: %s）。',
                                    $i,
                                    var_export($pv, true)
                                )
                            );
                        }
                        if ((float) $pv < 0) {
                            throw new RowImportFailedException(
                                sprintf(
                                    'options_payload.styles[%d].price_minor は 0 以上の整数である必要があります（入力値: %s）。',
                                    $i,
                                    var_export($pv, true)
                                )
                            );
                        }
                        $minor = MenuItemMoney::normalizePersistedOptionMinor($pv);
                        if ($minor < 0) {
                            throw new RowImportFailedException(
                                sprintf(
                                    'options_payload.styles[%d].price_minor は 0 以上の整数である必要があります（入力値: %s）。',
                                    $i,
                                    var_export($pv, true)
                                )
                            );
                        }
                        $styles[] = [
                            'id' => $id,
                            'name' => (string) ($style['name'] ?? ''),
                            'price_minor' => $minor,
                        ];
                    }

                    $usedToppingIds = [];
                    $toppings = [];
                    foreach ($decoded['toppings'] ?? [] as $i => $topping) {
                        if (! is_array($topping)) {
                            throw new RowImportFailedException(
                                sprintf('options_payload.toppings[%d] は配列である必要があります。', $i)
                            );
                        }
                        $name = trim((string) ($topping['name'] ?? ''));
                        $id = trim((string) ($topping['id'] ?? ''));
                        if ($id === '') {
                            $id = Str::slug($name) ?: 'topping-'.($i + 1);
                        }
                        $id = $makeUnique($id, $usedToppingIds);
                        $dv = $topping['price_delta_minor'] ?? 0;
                        if ($dv === null || $dv === '') {
                            $dv = 0;
                        }
                        if (is_string($dv)) {
                            $dv = trim($dv);
                            if ($dv === '') {
                                $dv = 0;
                            }
                        }
                        if (is_bool($dv) || is_array($dv)) {
                            throw new RowImportFailedException(
                                sprintf(
                                    'options_payload.toppings[%d].price_delta_minor は 0 以上の整数である必要があります（入力値: %s）。',
                                    $i,
                                    var_export($dv, true)
                                )
                            );
                        }
                        if (! is_numeric($dv)) {
                            throw new RowImportFailedException(
                                sprintf(
                                    'options_payload.toppings[%d].price_delta_minor は 0 以上の整数である必要があります（入力値: %s）。',
                                    $i,
                                    var_export($dv, true)
                                )
                            );
                        }
                        if ((float) $dv < 0) {
                            throw new RowImportFailedException(
                                sprintf(
                                    'options_payload.toppings[%d].price_delta_minor は 0 以上の整数である必要があります（入力値: %s）。',
                                    $i,
                                    var_export($dv, true)
                                )
                            );
                        }
                        $delta = MenuItemMoney::normalizePersistedOptionMinor($dv);
                        if ($delta < 0) {
                            throw new RowImportFailedException(
                                sprintf(
                                    'options_payload.toppings[%d].price_delta_minor は 0 以上の整数である必要があります（入力値: %s）。',
                                    $i,
                                    var_export($dv, true)
                                )
                            );
                        }
                        $toppings[] = [
                            'id' => $id,
                            'name' => (string) ($topping['name'] ?? ''),
                            'price_delta_minor' => $delta,
                        ];
                    }

                    if (! $styleRequired && $styles === [] && $toppings === []) {
                        return null;
                    }

                    return [
                        'rules' => ['style_required' => $styleRequired],
                        'styles' => array_values($styles),
                        'toppings' => array_values($toppings),
                    ];
                }),
        ];
    }

    public function resolveRecord(): ?MenuItem
    {
        $id = $this->data['id'] ?? null;
        if ($id === null || $id === '') {
            return new MenuItem;
        }

        // RowImportFailedException: filament/actions の ImportCsv が専用捕捉し、
        // logFailedRow($row, $exception->getMessage()) で失敗理由を failed_rows に残す。
        // ValidationException でも可だが、行単位の「ビジネス拒否」には本例外が意図された API（v3.3.49）。
        $record = MenuItem::query()->find($id);
        if (! $record) {
            throw new RowImportFailedException(
                sprintf(
                    '指定された id = %s の商品が存在しません。新規作成する場合は id 列を空にしてください。',
                    $id
                )
            );
        }

        return $record;
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
