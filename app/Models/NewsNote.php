<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class NewsNote extends Model
{
    protected $fillable = [
        'staff_id',
        'title',
        'body',
        'posted_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * 過去 $days 日間の投稿（新しい順）。
     *
     * @return Collection<int, self>
     */
    public static function recentDays(int $days = 5): Collection
    {
        return self::query()
            ->with('staff:id,name')
            ->where('posted_date', '>=', Carbon::today()->subDays($days - 1)->toDateString())
            ->orderByDesc('posted_date')
            ->orderByDesc('created_at')
            ->get();
    }
}
