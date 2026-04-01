<?php

namespace App\Filament\Resources\Staff\Forms;

use App\Models\Setting;
use App\Models\JobLevel;
use Closure;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;

class StaffForm
{
    public static function configure(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('shop_id')
                    ->relationship('shop', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('pin_code')
                    ->label('PIN')
                    ->tel()
                    ->maxLength(4)
                    ->nullable()
                    ->rules(['nullable', 'digits:4'])
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),
                Select::make('role')
                    ->options(function (): array {
                        $roles = Setting::getValue('staff_roles', ['hall', 'kitchen', 'manager', 'support']);

                        if (! is_array($roles)) {
                            return [];
                        }

                        $out = [];

                        foreach ($roles as $r) {
                            if (! is_string($r) || $r === '') {
                                continue;
                            }

                            $out[$r] = ucfirst($r);
                        }

                        return $out;
                    })
                    ->nullable(),
                Select::make('job_level_id')
                    ->label('ジョブレベル')
                    ->options(fn (): array => JobLevel::query()
                        ->orderBy('level')
                        ->get()
                        ->mapWithKeys(fn (JobLevel $jobLevel): array => [
                            $jobLevel->id => "{$jobLevel->level} - {$jobLevel->name}",
                        ])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->nullable(),
                TextInput::make('wage')
                    ->numeric()
                    ->step(0.01)
                    ->nullable(),
                TextInput::make('hourly_wage')
                    ->numeric()
                    ->label('時給')
                    ->suffix('DT')
                    ->nullable(),
                TextInput::make('target_weekly_hours')
                    ->numeric()
                    ->integer()
                    ->nullable(),
                Toggle::make('is_active')
                    ->default(true),
                Toggle::make('is_manager')
                    ->label('マネージャー権限')
                    ->helperText('クライアント側で出勤時間の修正を承認できます。'),
                Section::make('Horaires hebdomadaires (fixed_shifts)')
                    ->description('Horaires théoriques par jour (détection de retard au pointage : tolérance 10 minutes).')
                    ->schema([
                        Textarea::make('fixed_shifts')
                            ->json()
                            ->formatStateUsing(function ($state) {
                                $data = is_string($state) ? json_decode($state, true) : $state;
                                if (! is_array($data)) {
                                    return is_string($state)
                                        ? $state
                                        : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                }

                                $ordered = [];
                                $expectedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                foreach ($expectedDays as $day) {
                                    if (array_key_exists($day, $data)) {
                                        $ordered[$day] = $data[$day];
                                    }
                                }

                                return json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            })
                            ->dehydrateStateUsing(function ($state) {
                                $data = is_string($state) ? json_decode($state, true) : $state;
                                if (! is_array($data)) {
                                    return $data;
                                }

                                $ordered = [];
                                $expectedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                foreach ($expectedDays as $day) {
                                    if (array_key_exists($day, $data)) {
                                        $ordered[$day] = $data[$day];
                                    }
                                }

                                return $ordered;
                            })
                            ->rows(15)
                            ->hintAction(
                                \Filament\Forms\Components\Actions\Action::make('how_to_use')
                                    ->label('書き方のサンプル（説明書）')
                                    ->icon('heroicon-m-question-mark-circle')
                                    ->modalHeading('シフト（JSON）の書き方ルール')
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('閉じる')
                                    ->modalContent(fn () => new \Illuminate\Support\HtmlString('
                                        <div class="text-sm" style="line-height: 1.6;">
                                            <p>曜日（英語小文字）をキーにし、lunchとdinnerの時間を指定します。<br>時間は <b>["開始", "終了"]</b> の形式、休みのシフトは <b>null</b> を指定してください。</p>
                                            <ul class="list-disc pl-5 mt-2 mb-4">
                                                <li><b>両方勤務:</b> <code>"lunch": ["11:00", "15:00"], "dinner": ["18:00", "23:00"]</code></li>
                                                <li><b>ランチのみ:</b> <code>"lunch": ["11:00", "15:00"], "dinner": null</code></li>
                                                <li><b>ディナーのみ:</b> <code>"lunch": null, "dinner": ["18:00", "23:00"]</code></li>
                                                <li><b>休み:</b> <code>"lunch": null, "dinner": null</code></li>
                                            </ul>
                                            <p><b>【コピペ用テンプレート】</b></p>
                                            <pre style="background: #111827; color: #fff; padding: 10px; border-radius: 5px; overflow-x: auto;"><code>{
  "monday": { "lunch": ["11:00", "15:00"], "dinner": ["18:00", "23:00"] },
  "tuesday": { "lunch": ["11:00", "15:00"], "dinner": null },
  "wednesday": { "lunch": null, "dinner": ["18:00", "23:00"] },
  "thursday": { "lunch": null, "dinner": null },
  "friday": { "lunch": null, "dinner": null },
  "saturday": { "lunch": null, "dinner": null },
  "sunday": { "lunch": null, "dinner": null }
}</code></pre>
                                        </div>
                                    '))
                            )
                            ->default('{"monday":{"lunch":["10:00","15:00"],"dinner":null},"tuesday":{"lunch":null,"dinner":["18:00","23:30"]},"wednesday":{"lunch":["10:00","15:00"],"dinner":["18:00","23:30"]},"thursday":{"lunch":null,"dinner":null},"friday":{"lunch":null,"dinner":null},"saturday":{"lunch":null,"dinner":null},"sunday":{"lunch":null,"dinner":null}}')
                            ->columnSpanFull()
                            ->rules([
                                function (): Closure {
                                    return function (string $attribute, mixed $value, Closure $fail): void {
                                        try {
                                            if (is_string($value)) {
                                                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                                            } elseif (is_array($value)) {
                                                $decoded = $value;
                                            } else {
                                                $encoded = json_encode($value, JSON_THROW_ON_ERROR);
                                                $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
                                            }
                                        } catch (\Throwable) {
                                            $fail('有効なJSON文字列を入力してください。');

                                            return;
                                        }

                                        if (! is_array($decoded)) {
                                            $fail('JSONはオブジェクト形式で入力してください。');

                                            return;
                                        }

                                        $expectedDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                        $actualDays = array_keys($decoded);
                                        $missingDays = array_values(array_diff($expectedDays, $actualDays));
                                        $unknownDays = array_values(array_diff($actualDays, $expectedDays));

                                        if ($missingDays !== [] || $unknownDays !== []) {
                                            $fail('曜日キーは monday から sunday までの7つを指定してください。');

                                            return;
                                        }

                                        foreach ($expectedDays as $day) {
                                            $dayValue = $decoded[$day] ?? null;

                                            if (! is_array($dayValue) || ! array_key_exists('lunch', $dayValue) || ! array_key_exists('dinner', $dayValue)) {
                                                $fail("{$day} には lunch と dinner のキーが必要です。");

                                                return;
                                            }

                                            foreach (['lunch', 'dinner'] as $meal) {
                                                $slot = $dayValue[$meal];

                                                if ($slot === null) {
                                                    continue;
                                                }

                                                if (! is_array($slot) || count($slot) !== 2) {
                                                    $fail("{$day}.{$meal} は null または [\"HH:MM\", \"HH:MM\"] 形式で指定してください。");

                                                    return;
                                                }

                                                foreach ($slot as $time) {
                                                    if (! is_string($time) || preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $time) !== 1) {
                                                        $fail("{$day}.{$meal} の時刻は HH:MM 形式で指定してください。");

                                                        return;
                                                    }
                                                }
                                            }
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
