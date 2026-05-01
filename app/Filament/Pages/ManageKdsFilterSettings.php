<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\MenuCategory;
use App\Models\Shop;
use App\Support\KdsDictionarySetting;
use App\Support\KdsFilterSetting;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

final class ManageKdsFilterSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static string $view = 'filament.pages.manage-kds-filter-settings';

    protected static ?string $navigationLabel = 'KDS設定';

    protected static ?string $title = 'KDS設定';

    protected static ?string $navigationGroup = 'システム設定';

    protected static ?int $navigationSort = 22;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $sid = (int) Shop::query()->where('is_active', true)->orderBy('id')->value('id');
        if ($sid < 1) {
            $this->form->fill([
                'shop_id' => null,
                'kitchen_category_ids' => [],
                'hall_category_ids' => [],
                'kds_dictionary_text' => '',
            ]);

            return;
        }

        $this->form->fill([
            'shop_id' => $sid,
            'kitchen_category_ids' => KdsFilterSetting::kitchenCategoryIds($sid),
            'hall_category_ids' => KdsFilterSetting::hallCategoryIds($sid),
            'kds_dictionary_text' => KdsDictionarySetting::getText($sid),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('filament.kds_filter.section_title'))
                    ->description(__('filament.kds_filter.section_description'))
                    ->schema([
                        Select::make('shop_id')
                            ->label(__('filament.kds_filter.shop'))
                            ->options(fn (): array => Shop::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $sid = (int) $state;
                                if ($sid < 1) {
                                    return;
                                }
                                $set('kitchen_category_ids', KdsFilterSetting::kitchenCategoryIds($sid));
                                $set('hall_category_ids', KdsFilterSetting::hallCategoryIds($sid));
                                $set('kds_dictionary_text', KdsDictionarySetting::getText($sid));
                            }),
                        CheckboxList::make('kitchen_category_ids')
                            ->label(__('filament.kds_filter.kitchen_categories'))
                            ->options(fn (Get $get): array => $this->categoryOptionsForShop((int) $get('shop_id')))
                            ->bulkToggleable()
                            ->columns(2),
                        CheckboxList::make('hall_category_ids')
                            ->label(__('filament.kds_filter.hall_categories'))
                            ->options(fn (Get $get): array => $this->categoryOptionsForShop((int) $get('shop_id')))
                            ->bulkToggleable()
                            ->columns(2),
                    ]),
                Section::make('KDS表示変換辞書')
                    ->schema([
                        Textarea::make('kds_dictionary_text')
                            ->label('変換ルール (1行に「元の名前:変換後の名前」)')
                            ->helperText('例: Extra Spicy:激辛 / Wakame:wkm')
                            ->rows(10),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int|string, string>
     */
    private function categoryOptionsForShop(int $shopId): array
    {
        if ($shopId < 1) {
            return [];
        }

        return MenuCategory::query()
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (MenuCategory $c): array => [$c->id => $c->name])
            ->all();
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $sid = (int) ($state['shop_id'] ?? 0);
        if ($sid < 1) {
            Notification::make()
                ->title(__('filament.kds_filter.missing_shop'))
                ->danger()
                ->send();

            return;
        }
        $kitchen = $state['kitchen_category_ids'] ?? [];
        $hall = $state['hall_category_ids'] ?? [];
        if (! is_array($kitchen)) {
            $kitchen = [];
        }
        if (! is_array($hall)) {
            $hall = [];
        }
        KdsFilterSetting::saveKitchenCategoryIds($sid, $kitchen);
        KdsFilterSetting::saveHallCategoryIds($sid, $hall);
        $dictRaw = $state['kds_dictionary_text'] ?? '';
        $dictText = is_string($dictRaw) ? $dictRaw : '';
        KdsDictionarySetting::saveText($sid, $dictText);
        Cache::forget(KdsDictionarySetting::jsonCacheKey($sid));
        Notification::make()
            ->title(__('filament.kds_filter.saved'))
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            return false;
        }
        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');

        return $user->hasRole($superAdmin) || $user->hasRole('manager');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }
}
