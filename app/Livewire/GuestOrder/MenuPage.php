<?php

namespace App\Livewire\GuestOrder;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Exceptions\GuestOrderForbiddenException;
use App\Exceptions\GuestOrderValidationException;
use App\Models\MenuItem;
use App\Models\Shop;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.guest-order')]
class MenuPage extends Component
{
    /**
     * Ordered menu catalogue (DB-backed Shop → categories → items).
     *
     * @var array<string, mixed>
     */
    public array $catalog = [];

    /**
     * Tenant brand tokens used for :root CSS variable injection.
     * Ver1: dummy array. Ver2: replace resolveTheme() with Tenant model lookup.
     *
     * @var array<string, mixed>
     */
    public array $theme = [];

    /**
     * Flat UI-text dictionary for the current locale.
     * Alpine store reads this via $store.cart.t(key).
     *
     * @var array<string, string>
     */
    public array $translations = [];

    /** Route param: tenant slug (Ver2: resolve tenant / brand id). */
    public string $tenantSlug = '';

    /** Route param: opaque table token from QR (Ver2: validate + bind session). */
    public string $tableToken = '';

    public function mount(string $tenantSlug, string $tableToken): void
    {
        $this->tenantSlug = $tenantSlug;
        $this->tableToken = $tableToken;
        $this->theme = $this->resolveTheme($this->themeKeyForLookup($tenantSlug));
        $this->catalog = $this->resolveCatalog($tenantSlug);
        $this->translations = $this->resolveTranslations();
    }

    public function render(): View
    {
        return view('livewire.guest-order.menu-page');
    }

    /**
     * Persist guest cart as a POS order (Zero Trust pricing in {@see SubmitGuestOrderAction}).
     *
     * @param  array<string, mixed>  $payload
     */
    public function submitOrder(array $payload): void
    {
        try {
            $result = app(SubmitGuestOrderAction::class)->execute(
                $this->tenantSlug,
                $this->tableToken,
                $payload,
            );
            $this->dispatch('guest-order-saved', orderId: $result->posOrderId);
        } catch (GuestOrderForbiddenException|GuestOrderValidationException $e) {
            $this->dispatch('guest-order-error', message: $e->getMessage());
        } catch (Throwable $e) {
            Log::error('guest_order_submit_failed', [
                'exception' => $e,
            ]);
            $this->dispatch('guest-order-error', message: __('Unable to submit order. Please try again.'));
        }
    }

    // ─── Resolution methods (Ver2: replace bodies with real lookups) ──────────

    /**
     * DB `shops.slug` と theme 定義のキーが1対1で一致しないと `resolveTheme` だけ
     * Bistro フォールバックになり、ヘッダ/配色は別店・カタログは正店という不整合が出る。
     * ここで正規化する（例: ショップ表が `currykitano`、テーマ定義は `curry-kitano`）。
     */
    private function themeKeyForLookup(string $tenantSlug): string
    {
        return match (trim($tenantSlug)) {
            'currykitano' => 'curry-kitano',
            default => $tenantSlug,
        };
    }

    /**
     * Resolve brand tokens for a given theme key (after {@see themeKeyForLookup}).
     *
     * @return array<string, mixed>
     */
    private function resolveTheme(string $themeKey): array
    {
        $themes = [
            'bistronippon' => [
                'slug' => 'bistronippon',
                'display_name' => 'Bistro Nippon.',
                'logo_url' => asset('images/tenants/bistronippon/logo.svg'),
                'primary_hex' => '#1e3a8a',
                'on_primary_hex' => '#ffffff',
                'accent_hex' => '#3b82f6',
                'danger_hex' => '#dc2626',
                'surface_hex' => '#f8fafc',
                'cart_bg_hex' => '#0f172a',
                'button_radius_rem' => '0.75rem',
                'card_radius_rem' => '1rem',
                'font_url' => 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap',
                'font_family' => "'Noto Sans JP', 'Instrument Sans', ui-sans-serif, sans-serif",
            ],
            'soya' => [
                'slug' => 'soya',
                'display_name' => 'Söya',
                'logo_url' => asset('images/tenants/soya/logo.svg'),
                'primary_hex' => '#14532d',
                'on_primary_hex' => '#ffffff',
                'accent_hex' => '#22c55e',
                'danger_hex' => '#dc2626',
                'surface_hex' => '#fafaf5',
                'cart_bg_hex' => '#1a2e1a',
                'button_radius_rem' => '1.5rem',
                'card_radius_rem' => '1.25rem',
                'font_url' => 'https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap',
                'font_family' => "'Zen Kaku Gothic New', 'Instrument Sans', ui-sans-serif, sans-serif",
            ],
            'curry-kitano' => [
                'slug' => 'curry-kitano',
                'display_name' => 'Curry Kitano',
                'logo_url' => asset('images/tenants/curry-kitano/logo.svg'),
                'primary_hex' => '#7c2d12',
                'on_primary_hex' => '#ffffff',
                'accent_hex' => '#f97316',
                'danger_hex' => '#dc2626',
                'surface_hex' => '#fff8f0',
                'cart_bg_hex' => '#431407',
                'button_radius_rem' => '0.5rem',
                'card_radius_rem' => '0.75rem',
                'font_url' => 'https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap',
                'font_family' => "'Noto Serif JP', ui-serif, serif",
            ],
        ];

        return $themes[$themeKey] ?? $themes['bistronippon'];
    }

    /**
     * Resolve the ordered menu catalogue for a given tenant slug (Shop.slug).
     * Display price uses min(base from_price_minor, min(styles[].price_minor)).
     *
     * @return array<string, mixed>
     */
    private function resolveCatalog(string $tenantSlug): array
    {
        $shop = Shop::query()
            ->where('slug', $tenantSlug)
            ->where('is_active', true)
            ->with([
                'menuCategories' => static function ($query): void {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name');
                },
                'menuCategories.menuItems' => static function ($query): void {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name');
                },
            ])
            ->first();

        if ($shop === null) {
            return [
                'meta' => self::defaultMeta(null),
                'categories' => [],
            ];
        }

        $categories = [];
        foreach ($shop->menuCategories as $category) {
            $items = [];
            foreach ($category->menuItems as $item) {
                $items[] = $this->mapMenuItemToCatalogArray($item);
            }

            $categories[] = [
                'id' => $this->catalogIdForCategory($category->slug, $category->getKey()),
                'label' => $category->name,
                'items' => $items,
            ];
        }

        return [
            'meta' => self::defaultMeta($shop->id),
            'categories' => $categories,
        ];
    }

    /**
     * @return array{currency: string, price_divisor: int, merge_identical_lines: bool, shop_id?: int}
     */
    private static function defaultMeta(?int $shopId = null): array
    {
        $meta = [
            'currency' => 'TND',
            'price_divisor' => 1000,
            'merge_identical_lines' => true,
        ];
        if ($shopId !== null && $shopId > 0) {
            $meta['shop_id'] = $shopId;
        }

        return $meta;
    }

    private function catalogIdForCategory(?string $slug, int|string $id): string
    {
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        return (string) $id;
    }

    private function catalogIdForMenuItem(int|string $id): string
    {
        return (string) $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMenuItemToCatalogArray(MenuItem $item): array
    {
        $payload = is_array($item->options_payload) ? $item->options_payload : [];
        $styles = array_values(array_filter(
            is_array($payload['styles'] ?? null) ? $payload['styles'] : [],
            static fn ($row): bool => is_array($row),
        ));
        $toppings = array_values(array_filter(
            is_array($payload['toppings'] ?? null) ? $payload['toppings'] : [],
            static fn ($row): bool => is_array($row),
        ));

        $rules = array_merge(
            [
                'style_required' => false,
                'merge_identical_lines' => true,
            ],
            is_array($payload['rules'] ?? null) ? $payload['rules'] : [],
        );

        $fromMinor = max(0, (int) $item->from_price_minor);
        foreach ($styles as $styleRow) {
            if (! is_array($styleRow) || ! array_key_exists('price_minor', $styleRow)) {
                continue;
            }
            $pm = max(0, (int) $styleRow['price_minor']);
            $fromMinor = min($fromMinor, $pm);
        }

        $kitchen = $item->kitchen_name;
        if ($kitchen === null || trim((string) $kitchen) === '') {
            $kitchen = $item->name;
        }

        return [
            'id' => $this->catalogIdForMenuItem($item->getKey()),
            'name' => $item->name,
            'kitchen_name' => $kitchen,
            'description' => (string) ($item->description ?? ''),
            'image' => $this->heroImageUrl($item),
            'from_price_minor' => $fromMinor,
            'styles' => $styles,
            'toppings' => $toppings,
            'rules' => $rules,
        ];
    }

    private function heroImageUrl(MenuItem $item): string
    {
        $path = $item->hero_image_path;
        if ($path === null || $path === '') {
            return '';
        }

        $disk = $item->hero_image_disk !== '' ? $item->hero_image_disk : 'public';

        return Storage::disk($disk)->url($path);
    }

    /**
     * Build the flat translation dictionary for the current locale.
     *
     * @return array<string, string>
     */
    private function resolveTranslations(): array
    {
        $keys = [
            'select_style',
            'required',
            'toppings',
            'add_to_order',
            'to_cart',
            'total',
            'please_select_style',
            'order_sent',
            'call_server',
            'styles_badge',
            'from_price',
            'close',
            'your_order',
            'empty_cart',
            'continue_shopping',
            'qty',
            'remove_line',
            'cart_modal_title',
            'clear_cart',
            'clear_cart_tap_again',
            'order_submit',
            'order_flow_notice',
            'undo',
            'item_removed',
        ];

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = Lang::has("guest-order.{$key}")
                ? __("guest-order.{$key}")
                : $key;
        }

        return $result;
    }
}
