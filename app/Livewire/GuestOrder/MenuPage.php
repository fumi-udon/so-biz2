<?php

namespace App\Livewire\GuestOrder;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Lang;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest-order')]
class MenuPage extends Component
{
    /**
     * Ordered menu catalogue.
     * Ver1: dummy array. Ver2: replace resolveMenu() with DB/Filament query.
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

    public function mount(string $tenantSlug, string $tableToken): void
    {
        $this->theme        = $this->resolveTheme($tenantSlug);
        $this->catalog      = $this->resolveCatalog($tenantSlug);
        $this->translations = $this->resolveTranslations();
    }

    public function render(): View
    {
        return view('livewire.guest-order.menu-page');
    }

    // ─── Resolution methods (Ver2: replace bodies with real lookups) ──────────

    /**
     * Resolve brand tokens for a given tenant slug.
     *
     * @return array<string, mixed>
     */
    private function resolveTheme(string $tenantSlug): array
    {
        $themes = [
            'bistronippon' => [
                'slug'              => 'bistronippon',
                'display_name'      => 'Bistro Nippon.',
                'logo_url'          => asset('images/tenants/bistronippon/logo.svg'),
                'primary_hex'       => '#1e3a8a',
                'on_primary_hex'    => '#ffffff',
                'accent_hex'        => '#3b82f6',
                'danger_hex'        => '#dc2626',
                'surface_hex'       => '#f8fafc',
                'cart_bg_hex'       => '#0f172a',
                'button_radius_rem' => '0.75rem',
                'card_radius_rem'   => '1rem',
                'font_url'          => 'https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap',
                'font_family'       => "'Noto Sans JP', 'Instrument Sans', ui-sans-serif, sans-serif",
            ],
            'soya' => [
                'slug'              => 'soya',
                'display_name'      => 'Söya',
                'logo_url'          => asset('images/tenants/soya/logo.svg'),
                'primary_hex'       => '#14532d',
                'on_primary_hex'    => '#ffffff',
                'accent_hex'        => '#22c55e',
                'danger_hex'        => '#dc2626',
                'surface_hex'       => '#fafaf5',
                'cart_bg_hex'       => '#1a2e1a',
                'button_radius_rem' => '1.5rem',
                'card_radius_rem'   => '1.25rem',
                'font_url'          => 'https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap',
                'font_family'       => "'Zen Kaku Gothic New', 'Instrument Sans', ui-sans-serif, sans-serif",
            ],
            'curry-kitano' => [
                'slug'              => 'curry-kitano',
                'display_name'      => 'Curry Kitano',
                'logo_url'          => asset('images/tenants/curry-kitano/logo.svg'),
                'primary_hex'       => '#7c2d12',
                'on_primary_hex'    => '#ffffff',
                'accent_hex'        => '#f97316',
                'danger_hex'        => '#dc2626',
                'surface_hex'       => '#fff8f0',
                'cart_bg_hex'       => '#431407',
                'button_radius_rem' => '0.5rem',
                'card_radius_rem'   => '0.75rem',
                'font_url'          => 'https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap',
                'font_family'       => "'Noto Serif JP', ui-serif, serif",
            ],
        ];

        return $themes[$tenantSlug] ?? $themes['bistronippon'];
    }

    /**
     * Resolve the ordered menu catalogue for a given tenant slug.
     * from_price_minor is always min(styles[].price_minor) for display.
     *
     * @return array<string, mixed>
     */
    private function resolveCatalog(string $tenantSlug): array
    {
        // Shared dummy catalogue (same across all tenants for Ver1)
        return [
            'meta' => [
                'currency'              => 'TND',
                'price_divisor'         => 1000,
                'merge_identical_lines' => true,
            ],
            'categories' => [
                [
                    'id'    => 'entrees-tapas',
                    'label' => 'ENTRÉES & TAPAS',
                    'items' => [
                        [
                            'id'               => 'soupe-miso',
                            'name'             => 'Soupe miso wakame',
                            'description'      => 'Soupe miso aux algues vertes.',
                            'image'            => asset('images/dummy/soupe-miso.jpg'),
                            'from_price_minor' => 8000,
                            'styles'           => [
                                ['id' => 'classic', 'name' => 'Classic', 'price_minor' => 8000],
                            ],
                            'toppings' => [],
                            'rules'    => ['style_required' => false, 'merge_identical_lines' => true],
                        ],
                        [
                            'id'               => 'gyoza',
                            'name'             => 'Gyoza grillés',
                            'description'      => 'Raviolis grillés poulet bœuf.',
                            'image'            => asset('images/dummy/gyoza.jpg'),
                            'from_price_minor' => 12000,
                            'styles'           => [
                                ['id' => 'poulet',   'name' => 'Poulet',       'price_minor' => 12000],
                                ['id' => 'boeuf',    'name' => 'Bœuf',         'price_minor' => 13000],
                                ['id' => 'legumes',  'name' => 'Légumes',      'price_minor' => 11000],
                                ['id' => 'crevettes','name' => 'Crevettes',    'price_minor' => 14000],
                            ],
                            'toppings' => [],
                            'rules'    => ['style_required' => true, 'merge_identical_lines' => true],
                        ],
                        [
                            'id'               => 'croquettes',
                            'name'             => 'Croquettes (2 pièces)',
                            'description'      => 'Croquettes sauce teriyaki.',
                            'image'            => asset('images/dummy/croquettes.jpg'),
                            'from_price_minor' => 8000,
                            'styles'           => [
                                ['id' => 'default', 'name' => 'Classic', 'price_minor' => 8000],
                            ],
                            'toppings' => [],
                            'rules'    => ['style_required' => false, 'merge_identical_lines' => true],
                        ],
                        [
                            'id'               => 'paiko',
                            'name'             => 'Paiko poulet frit',
                            'description'      => 'Poulet frit sauce japonaise.',
                            'image'            => asset('images/dummy/paiko.jpg'),
                            'from_price_minor' => 19000,
                            'styles'           => [
                                ['id' => 'regular', 'name' => 'Regular',  'price_minor' => 19000],
                                ['id' => 'spicy',   'name' => 'Spicy',    'price_minor' => 20000],
                            ],
                            'toppings' => [
                                ['id' => 'extra-sauce', 'name' => 'Sauce supplémentaire', 'price_delta_minor' => 1500],
                            ],
                            'rules' => ['style_required' => true, 'merge_identical_lines' => true],
                        ],
                    ],
                ],
                [
                    'id'    => 'riz-rice',
                    'label' => 'RIZ / RICE',
                    'items' => [
                        [
                            'id'               => 'donburi-poulet',
                            'name'             => 'Donburi Poulet Teriyaki',
                            'description'      => 'Riz japonais, poulet teriyaki maison.',
                            'image'            => asset('images/dummy/donburi.jpg'),
                            'from_price_minor' => 22000,
                            'styles'           => [
                                ['id' => 'small',  'name' => 'Small  (S)', 'price_minor' => 22000],
                                ['id' => 'medium', 'name' => 'Medium (M)', 'price_minor' => 26000],
                                ['id' => 'large',  'name' => 'Large  (L)', 'price_minor' => 30000],
                            ],
                            'toppings' => [
                                ['id' => 'oeuf-onsen', 'name' => 'Œuf onsen', 'price_delta_minor' => 2500],
                            ],
                            'rules' => ['style_required' => true, 'merge_identical_lines' => true],
                        ],
                    ],
                ],
                [
                    'id'    => 'ramen',
                    'label' => 'RAMEN',
                    'items' => [
                        [
                            'id'               => 'ramen-tokyo',
                            'name'             => 'RAMEN TOKYO SAUCE SOJA',
                            'description'      => 'Bouillon sauce soja, œuf.',
                            'image'            => asset('images/dummy/ramen-tokyo.jpg'),
                            'from_price_minor' => 29000,
                            'styles'           => [
                                ['id' => 'chicken-veg', 'name' => 'poulet & légumes',  'price_minor' => 29000],
                                ['id' => 'paiko',       'name' => 'paiko poulet frits','price_minor' => 33000],
                                ['id' => 'beef-bbq',    'name' => 'bœuf bbq (150g)',   'price_minor' => 43000],
                                ['id' => 'seafood',     'name' => 'fruits de mer',     'price_minor' => 39000],
                                ['id' => 'shrimp',      'name' => 'crevettes',         'price_minor' => 35000],
                            ],
                            'toppings' => [
                                ['id' => 'wakame', 'name' => 'algues wakame',        'price_delta_minor' => 3500],
                                ['id' => 'spicy',  'name' => 'spicy',               'price_delta_minor' => 1000],
                                ['id' => 'diable', 'name' => 'diable spicy',        'price_delta_minor' => 2000],
                                ['id' => 'nori',   'name' => 'feuilles de nori 4p.','price_delta_minor' => 2500],
                                ['id' => 'menma',  'name' => 'pousses de bambou',   'price_delta_minor' => 2000],
                            ],
                            'rules' => ['style_required' => true, 'merge_identical_lines' => true],
                        ],
                        [
                            'id'               => 'ramen-sapporo-spicy',
                            'name'             => 'RAMEN SAPPORO MISO SPICY 🌶️',
                            'description'      => 'Bouillon miso pimenté, œuf.',
                            'image'            => asset('images/dummy/ramen-sapporo.jpg'),
                            'from_price_minor' => 29000,
                            'styles'           => [
                                ['id' => 'chicken-veg', 'name' => 'poulet & légumes',  'price_minor' => 29000],
                                ['id' => 'paiko',       'name' => 'paiko poulet frits','price_minor' => 33000],
                                ['id' => 'beef-bbq',    'name' => 'bœuf bbq (150g)',   'price_minor' => 43000],
                                ['id' => 'seafood',     'name' => 'fruits de mer',     'price_minor' => 39000],
                            ],
                            'toppings' => [
                                ['id' => 'wakame', 'name' => 'algues wakame',        'price_delta_minor' => 3500],
                                ['id' => 'spicy',  'name' => 'spicy',               'price_delta_minor' => 1000],
                                ['id' => 'diable', 'name' => 'diable spicy',        'price_delta_minor' => 2000],
                                ['id' => 'nori',   'name' => 'feuilles de nori 4p.','price_delta_minor' => 2500],
                            ],
                            'rules' => ['style_required' => true, 'merge_identical_lines' => true],
                        ],
                        [
                            'id'               => 'ramen-tantan',
                            'name'             => 'RAMEN TANTAN SPICY',
                            'description'      => 'Bouillon sésame miso, bœuf.',
                            'image'            => asset('images/dummy/ramen-tantan.jpg'),
                            'from_price_minor' => 28000,
                            'styles'           => [
                                ['id' => 'chicken-veg', 'name' => 'poulet & légumes',  'price_minor' => 28000],
                                ['id' => 'beef',        'name' => 'bœuf',              'price_minor' => 35000],
                                ['id' => 'seafood',     'name' => 'fruits de mer',     'price_minor' => 36000],
                            ],
                            'toppings' => [
                                ['id' => 'wakame', 'name' => 'algues wakame', 'price_delta_minor' => 3500],
                                ['id' => 'spicy',  'name' => 'spicy',        'price_delta_minor' => 1000],
                            ],
                            'rules' => ['style_required' => true, 'merge_identical_lines' => true],
                        ],
                    ],
                ],
                [
                    'id'    => 'udon',
                    'label' => 'UDON',
                    'items' => [
                        [
                            'id'               => 'udon-nippon',
                            'name'             => 'Udon Nippon au gingembre',
                            'description'      => 'Bouillon gingembre sauce soja.',
                            'image'            => asset('images/dummy/udon.jpg'),
                            'from_price_minor' => 30000,
                            'styles'           => [
                                ['id' => 'chicken-veg', 'name' => 'poulet & légumes', 'price_minor' => 30000],
                                ['id' => 'paiko',       'name' => 'paiko frits',      'price_minor' => 34000],
                                ['id' => 'beef-bbq',    'name' => 'bœuf bbq (150g)',  'price_minor' => 44000],
                                ['id' => 'seafood',     'name' => 'fruits de mer',    'price_minor' => 40000],
                                ['id' => 'ebi',         'name' => 'crevettes ebi',    'price_minor' => 36000],
                                ['id' => 'duck',        'name' => 'canard teriyaki',  'price_minor' => 42000],
                                ['id' => 'salmon',      'name' => 'saumon grillé',    'price_minor' => 38000],
                                ['id' => 'tofu',        'name' => 'tofu végé',        'price_minor' => 28000],
                            ],
                            'toppings' => [
                                ['id' => 'wakame', 'name' => 'algues wakame',        'price_delta_minor' => 3500],
                                ['id' => 'nori',   'name' => 'feuilles de nori 4p.','price_delta_minor' => 2500],
                                ['id' => 'spicy',  'name' => 'spicy',               'price_delta_minor' => 1000],
                            ],
                            'rules' => ['style_required' => true, 'merge_identical_lines' => true],
                        ],
                    ],
                ],
            ],
        ];
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
            'close',
            'your_order',
            'empty_cart',
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
