@php
    $h = intdiv($stats['total_minutes'], 60);
    $m = $stats['total_minutes'] % 60;
@endphp

{{-- Tailwind に依存しない: table + インライン CSS のみ --}}
<div style="margin:0 0 16px 0; width:100%; max-width:100%; box-sizing:border-box; overflow:hidden; border-radius:16px; border:1px solid #d6d3d1; background:linear-gradient(180deg,#ffffff 0%,#fafaf9 100%); box-shadow:0 1px 0 0 rgba(15,23,42,0.06); font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif; color:#1c1917;">
    <div style="height:4px; width:100%; background:linear-gradient(90deg,#6366f1,#8b5cf6,#d946ef);" aria-hidden="true"></div>

    <div style="padding:16px 20px 20px 20px;">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; margin-bottom:0;">
            <tr>
                <td style="vertical-align:top; padding:0;">
                    <p style="margin:0; font-size:11px; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:#78716c;">条件サマリー</p>
                </td>
                <td style="vertical-align:top; text-align:right; padding:0 0 0 12px; font-size:12px; color:#78716c;">
                    スタッフ・表示月の条件に一致する行のみ集計
                </td>
            </tr>
        </table>
        <h2 style="margin:8px 0 0 0; font-size:16px; font-weight:700; color:#0c0a09;">フィルター条件の集計</h2>
        <p style="margin:4px 0 0 0; font-size:12px; color:#57534e;">給与・勤怠チェック用のクイックサマリー</p>

        <div style="margin-top:20px; width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch;">
            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; min-width:640px; border-collapse:separate; border-spacing:12px 0;">
                <tr>
                    <td style="vertical-align:top; width:28%; min-width:160px; border-radius:12px; border:1px solid #e7e5e4; background:#ffffff; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                        <div style="font-size:11px; font-weight:500; color:#78716c;">月間労働時間（合計）</div>
                        <div style="margin-top:8px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:26px; font-weight:700; color:#0c0a09; letter-spacing:-0.02em;">
                            {{ $h }}<span style="font-size:18px; font-weight:600;">h</span>
                            {{ sprintf('%02d', $m) }}<span style="font-size:18px; font-weight:600;">m</span>
                        </div>
                        <div style="margin-top:6px; font-family:ui-monospace,Menlo,Consolas,monospace; font-size:12px; color:#78716c;">
                            小数 {{ number_format($stats['total_hours_decimal'], 2, '.', '') }} h
                        </div>
                    </td>
                    <td style="vertical-align:top; width:22%; min-width:140px; border-radius:12px; border:1px solid #e7e5e4; background:#ffffff; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                        <div style="font-size:11px; font-weight:500; color:#78716c;">遅刻回数（日）</div>
                        <div style="margin-top:8px;">
                            <span style="font-family:ui-monospace,Menlo,Consolas,monospace; font-size:26px; font-weight:700; color:#0c0a09;">{{ $stats['late_count'] }}</span>
                            <span style="font-size:14px; font-weight:500; color:#78716c; margin-left:4px;">回</span>
                        </div>
                        <div style="margin-top:6px; font-size:12px; color:#57534e;">
                            遅刻計 <span style="font-family:ui-monospace,Menlo,Consolas,monospace;">{{ $stats['late_total_minutes'] }}</span> 分
                        </div>
                    </td>
                    <td style="vertical-align:top; width:16%; min-width:100px; border-radius:12px; border:1px solid #e7e5e4; background:#ffffff; padding:16px; box-shadow:0 1px 2px rgba(0,0,0,0.04);">
                        <div style="font-size:11px; font-weight:500; color:#78716c;">対象行数（日）</div>
                        <div style="margin-top:8px;">
                            <span style="font-family:ui-monospace,Menlo,Consolas,monospace; font-size:26px; font-weight:700; color:#0c0a09;">{{ $stats['day_count'] }}</span>
                            <span style="font-size:14px; font-weight:500; color:#78716c; margin-left:4px;">件</span>
                        </div>
                    </td>
                    <td style="vertical-align:middle; min-width:180px; border-radius:12px; border:1px dashed #d6d3d1; background:#fafaf9; padding:12px 14px; font-size:11px; line-height:1.55; color:#57534e;">
                        各行の「当日労働」「時間(小数)」は1日分です。スタッフ・表示月で絞り込むと上記がその範囲の合計になります。
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
