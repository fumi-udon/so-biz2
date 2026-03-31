<?php

namespace App\Filament\Resources\DailyTipAudits;

use App\Filament\Resources\DailyTipAudits\Pages\ListDailyTipAudits;
use App\Models\DailyTipAudit;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyTipAuditResource extends Resource
{
    protected static ?string $model = DailyTipAudit::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Pourboires';

    protected static ?string $modelLabel = 'Tip Audit';

    protected static ?string $pluralModelLabel = 'Tip Audits';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'daily-tip-audits';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('日時')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable()
                    ->extraAttributes(['class' => 'text-xs font-mono']),
                TextColumn::make('user.name')
                    ->label('実行者')
                    ->default('system')
                    ->weight('bold')
                    ->searchable()
                    ->extraAttributes(['class' => 'text-xs']),
                TextColumn::make('action')
                    ->label('操作')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'daily_tip_created', 'distribution_created' => '🟢 '.$state,
                        'daily_tip_updated', 'distribution_updated', 'distribution_recalculated' => '🟡 '.$state,
                        'daily_tip_deleted', 'distribution_deleted' => '🔴 '.$state,
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'daily_tip_created', 'distribution_created' => 'success',
                        'daily_tip_updated', 'distribution_updated', 'distribution_recalculated' => 'warning',
                        'daily_tip_deleted', 'distribution_deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('target_date')
                    ->label('対象日')
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'text-xs']),
                TextColumn::make('shift')
                    ->label('シフト')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray')
                    ->extraAttributes(['class' => 'text-xs']),
                TextColumn::make('details')
                    ->label('詳細')
                    ->formatStateUsing(function (mixed $state): string {
                        $details = is_array($state) ? $state : [];
                        if ($details === []) {
                            return '—';
                        }

                        $parts = [];
                        foreach (['daily_tip_id', 'distribution_id', 'staff_id', 'removed_count', 'created_count', 'final_total_amount'] as $key) {
                            if (array_key_exists($key, $details)) {
                                $parts[] = $key.': '.$details[$key];
                            }
                        }

                        if (isset($details['before']) && is_array($details['before'])) {
                            $parts[] = 'before='.json_encode($details['before'], JSON_UNESCAPED_UNICODE);
                        }
                        if (isset($details['after']) && is_array($details['after'])) {
                            $parts[] = 'after='.json_encode($details['after'], JSON_UNESCAPED_UNICODE);
                        }

                        return $parts !== [] ? implode(' | ', $parts) : '—';
                    })
                    ->wrap()
                    ->extraAttributes(['class' => 'text-xs leading-tight']),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyTipAudits::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }
}
