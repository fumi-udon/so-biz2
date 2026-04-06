@php
    /** @var string $businessDate */
    /** @var \Illuminate\Support\Collection<int, array{staff: \App\Models\Staff, attendance: \App\Models\Attendance|null, status: string}> $staffRows */
@endphp

{{-- Tailwind に依存しない: インライン CSS のみ --}}
<div style="margin:0 0 20px 0; width:100%; max-width:100%; box-sizing:border-box; overflow:hidden; border-radius:16px; border:1px solid #d6d3d1; background:#fafaf9; box-shadow:0 1px 0 0 rgba(15,23,42,0.06); font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; color:#1c1917;">
    <div style="height:4px; width:100%; background:linear-gradient(90deg,#f59e0b,#f97316,#fb7185);" aria-hidden="true"></div>

    <div style="border-bottom:1px solid #e7e5e4; padding:16px 20px;">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="vertical-align:top; padding:0;">
                    <div style="display:inline-block; padding:4px 10px; border-radius:999px; border:1px solid rgba(16,185,129,0.35); background:rgba(16,185,129,0.1); font-size:10px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:#065f46;">
                        本日の名簿
                    </div>
                    <h3 style="margin:10px 0 0 0; font-size:18px; font-weight:700; line-height:1.25; color:#0c0a09;">
                        本日の出勤状況
                    </h3>
                    <p style="margin:6px 0 0 0; font-size:14px; color:#57534e;">
                        営業日（6:00 基準）
                        <time style="margin-left:6px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:14px; font-weight:600; color:#0c0a09;" datetime="{{ $businessDate }}">{{ $businessDate }}</time>
                    </p>
                </td>
                <td style="vertical-align:bottom; text-align:right; white-space:nowrap; padding:0 0 0 12px; font-size:12px; color:#57534e;">
                    <span style="display:inline-block; margin-left:12px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10b981; vertical-align:middle; margin-right:6px;"></span>勤務中</span>
                    <span style="display:inline-block; margin-left:12px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#a8a29e; vertical-align:middle; margin-right:6px;"></span>退勤済</span>
                    <span style="display:inline-block; margin-left:12px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#0ea5e9; vertical-align:middle; margin-right:6px;"></span>休業日</span>
                    <span style="display:inline-block; margin-left:12px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#f59e0b; vertical-align:middle; margin-right:6px;"></span>未確定</span>
                    <span style="display:inline-block; margin-left:12px;"><span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#dc2626; vertical-align:middle; margin-right:6px;"></span>欠勤</span>
                </td>
            </tr>
        </table>
    </div>

    <div style="padding:16px 18px 20px 18px; box-sizing:border-box;">
        @if ($staffRows->isEmpty())
            <p style="margin:0; font-size:14px; color:#78716c;">アクティブなスタッフがいません。</p>
        @else
            <div style="display:flex; flex-wrap:nowrap; gap:12px; max-width:100%; overflow-x:auto; overflow-y:hidden; padding:4px 2px 8px 2px; -webkit-overflow-scrolling:touch;">
                @foreach ($staffRows as $row)
                    @php
                        $isWorking = $row['status'] === 'working';
                        $st = $row['status'];
                    @endphp
                    <article style="flex:0 0 auto; width:min(256px, 85vw); min-width:220px; max-width:280px; box-sizing:border-box; border-radius:12px; border:1px solid {{ $isWorking ? 'rgba(16,185,129,0.55)' : '#d6d3d1' }}; padding:14px; background:{{ $isWorking ? 'linear-gradient(145deg,#ecfdf5,#ffffff)' : '#f5f5f4' }}; box-shadow:{{ $isWorking ? '0 0 0 1px rgba(16,185,129,0.15)' : 'none' }};">
                        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="vertical-align:top; padding:0; font-size:14px; font-weight:600; color:#0c0a09; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $row['staff']->name }}">{{ $row['staff']->name }}</td>
                                <td style="vertical-align:top; text-align:right; padding:0 0 0 8px; white-space:nowrap;">
                                    @if ($st === 'working')
                                        <span style="display:inline-block; padding:3px 8px; border-radius:6px; background:#059669; color:#fff; font-size:10px; font-weight:700; letter-spacing:0.04em;">勤務中</span>
                                    @elseif ($st === 'off')
                                        <span style="display:inline-block; padding:3px 8px; border-radius:6px; background:#a8a29e; color:#fff; font-size:10px; font-weight:700; letter-spacing:0.04em;">退勤済</span>
                                    @elseif ($st === 'holiday')
                                        <span style="display:inline-block; padding:3px 8px; border-radius:6px; background:#0284c7; color:#fff; font-size:10px; font-weight:700; letter-spacing:0.04em;">休業日</span>
                                    @elseif ($st === 'absent')
                                        <span style="display:inline-block; padding:3px 8px; border-radius:6px; background:#b91c1c; color:#fff; font-size:10px; font-weight:700; letter-spacing:0.04em;">欠勤</span>
                                    @else
                                        <span style="display:inline-block; padding:3px 8px; border-radius:6px; background:#d97706; color:#fff; font-size:10px; font-weight:700; letter-spacing:0.04em;">未確定</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                        @if ($row['attendance'])
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; margin-top:12px; padding-top:12px; border-top:1px solid #e7e5e4; font-size:11px; line-height:1.45; color:#44403c;">
                                <tr>
                                    <td style="vertical-align:top; width:22px; font-weight:700; color:#a8a29e; padding:4px 10px 4px 0;">L</td>
                                    <td style="vertical-align:top; text-align:right; font-family:ui-monospace,Menlo,Consolas,monospace; padding:4px 0;">
                                        {{ $row['attendance']->lunch_in_at?->format('H:i') ?? '—' }}
                                        <span style="color:#a8a29e; margin:0 4px;">→</span>
                                        {{ $row['attendance']->lunch_out_at?->format('H:i') ?? '—' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="vertical-align:top; width:22px; font-weight:700; color:#a8a29e; padding:4px 10px 4px 0;">D</td>
                                    <td style="vertical-align:top; text-align:right; font-family:ui-monospace,Menlo,Consolas,monospace; padding:4px 0;">
                                        {{ $row['attendance']->dinner_in_at?->format('H:i') ?? '—' }}
                                        <span style="color:#a8a29e; margin:0 4px;">→</span>
                                        {{ $row['attendance']->dinner_out_at?->format('H:i') ?? '—' }}
                                    </td>
                                </tr>
                            </table>
                        @else
                            <p style="margin:12px 0 0 0; padding-top:12px; border-top:1px dashed #d6d3d1; font-size:12px; color:#78716c;">
                                @if ($st === 'holiday')
                                    店舗休業日（打刻なし）
                                @elseif ($st === 'absent')
                                    確定欠勤（打刻なし）
                                @else
                                    打刻なし・未確定（欠勤登録がない場合は確定しません）
                                @endif
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
