<?php

namespace App\Models;

use App\Enums\MenuItemRoleCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MenuItem extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'menu_category_id',
        'name',
        'kitchen_name',
        'slug',
        'description',
        'hero_image_disk',
        'hero_image_path',
        'from_price_minor',
        'sort_order',
        'is_active',
        'role_category',
        'allergy_note',
        'options_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'options_payload' => 'array',
            'role_category' => MenuItemRoleCategory::class,
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * @return BelongsToMany<DietaryBadge, $this>
     */
    public function dietaryBadges(): BelongsToMany
    {
        return $this->belongsToMany(DietaryBadge::class, 'menu_item_dietary_badge')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    protected static function booted(): void
    {
        static::creating(function (MenuItem $model): void {
            if (trim((string) ($model->slug ?? '')) === '') {
                $model->slug = static::makeUniqueSlugForShop((int) $model->shop_id, (string) $model->name, null);
            }
        });

        static::updating(function (MenuItem $model): void {
            if (! $model->isDirty('slug')) {
                return;
            }
            if (trim((string) ($model->slug ?? '')) === '') {
                $model->slug = static::makeUniqueSlugForShop((int) $model->shop_id, (string) $model->name, $model->getKey());
            }
        });

        static::deleting(function (MenuItem $model): void {
            if ($model->isForceDeleting()) {
                return;
            }
            $suffix = '-deleted-'.$model->getKey().'-'.now()->getTimestamp();
            $base = (string) $model->slug;
            $maxBase = 255 - strlen($suffix);
            if ($maxBase < 1) {
                $model->slug = substr($suffix, -255);

                return;
            }
            if (strlen($base) > $maxBase) {
                $base = substr($base, 0, $maxBase);
            }
            $model->slug = $base.$suffix;
        });
    }

    /**
     * URL-safe slug unique per shop (active + soft-deleted rows keep distinct slugs after delete suffix).
     */
    public static function makeUniqueSlugForShop(int $shopId, string $name, int|string|null $ignoreId): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'item';
        }

        $candidate = $base;
        $n = 2;
        while (static::query()
            ->withTrashed()
            ->where('shop_id', $shopId)
            ->when($ignoreId !== null, static fn (Builder $q): Builder => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$n;
            $n++;
            if (strlen($candidate) > 255) {
                $candidate = substr($base, 0, 200).'-'.$n;
            }
        }

        return $candidate;
    }
}
