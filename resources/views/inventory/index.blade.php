<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>棚卸しポータル — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <x-client-nav />

    <div class="mx-auto w-full max-w-5xl px-4 py-5">
        <header class="mb-4">
            <h1 class="mb-2 text-2xl font-black text-slate-900">棚卸しポータル</h1>
            <p class="text-sm text-slate-600">
                営業日 <span class="font-mono font-semibold text-slate-900">{{ $dateString }}</span>
            </p>
        </header>

        @if (session('status'))
            <div class="mb-3 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-3 rounded-xl border border-rose-300 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ session('error') }}</div>
        @endif

        @if (empty($timingSections))
            <div class="rounded-xl border-2 border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                <p class="mb-2 text-3xl">📦</p>
                表示する棚卸し品目がありません。
            </div>
        @else
            @foreach ($timingSections as $section)
                @php
                    $timingLabel = $section['timing'] !== '' ? $section['timing'] : '（タイミング未設定）';
                @endphp
                <section class="mb-5 rounded-2xl border-2 border-black bg-gradient-to-br from-cyan-100 via-sky-100 to-indigo-100 p-3 shadow-[0_6px_0_0_rgba(0,0,0,1)]">
                    <h2 class="mb-3 flex items-center gap-2 text-lg font-black text-slate-900">
                        <span class="rounded-md bg-black px-2 py-0.5 text-xs tracking-wider text-cyan-200">TIMING</span>
                        <span class="font-mono">{{ $timingLabel }}</span>
                    </h2>

                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        @foreach ($section['rows'] as $row)
                            <article class="rounded-xl border-2 border-black bg-white p-3 shadow-[0_4px_0_0_rgba(0,0,0,1)]">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="truncate text-base font-black text-slate-900">{{ $row['staff_name'] }}</p>
                                    @if ($row['complete'])
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">済</span>
                                    @else
                                        <span class="inline-flex rounded-full border border-rose-300 bg-rose-50 px-2.5 py-1 text-xs font-black text-rose-700">未実施</span>
                                    @endif
                                </div>
                                <p class="mb-2 text-sm text-slate-600">
                                    進捗: <span class="font-mono font-bold text-slate-900">{{ $row['filled'] }} / {{ $row['total'] }}</span>
                                </p>
                                @if ($row['total'] > 0)
                                    <a
                                        href="{{ route('inventory.input', ['timing' => $section['timing'], 'staff_id' => $row['staff_id']]) }}"
                                        class="inline-flex w-full items-center justify-center rounded-lg border-2 px-3 py-2 text-sm font-black {{ $row['complete'] ? 'border-slate-300 bg-slate-100 text-slate-700' : 'border-black bg-indigo-600 text-white' }}"
                                    >
                                        {{ $row['complete'] ? '再入力する' : '実施開始' }}
                                    </a>
                                @else
                                    <p class="text-sm font-semibold text-slate-400">入力対象なし</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
</body>
</html>
