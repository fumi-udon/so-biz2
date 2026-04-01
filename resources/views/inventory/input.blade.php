<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>棚卸し入力（{{ $timing }}） — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important;}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <x-client-nav />

    <div class="mx-auto w-full max-w-xl px-4 py-5">
        <header class="mb-4">
            <h1 class="mb-1 text-2xl font-black">棚卸し入力</h1>
            <p class="text-sm text-slate-600">
                <span class="font-mono">{{ $dateString }}</span>
                · タイミング <span class="font-mono">{{ $timing }}</span>
            </p>
        </header>

        @if (session('error'))
            <div class="mb-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('inventory.store') }}">
            @csrf
            <input type="hidden" name="staff_id" value="{{ $staff->id }}">
            <input type="hidden" name="timing" value="{{ $timing }}">

            <div class="mb-4 overflow-x-auto rounded-xl border border-slate-300 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-3 py-2 text-sm font-bold text-slate-700">品目</div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600" style="width: 52%; min-width: 8rem;">品目 / カテゴリ</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600" style="width: 48%; min-width: 7rem;">入力</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inventoryItems as $item)
                                @php
                                    $inputType = $item->input_type ?? 'number';
                                @endphp
                                <tr class="border-t border-slate-100">
                                    <td class="px-3 py-2">
                                        <div class="font-semibold break-words">{{ $item->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $item->category }}</div>
                                        @if ($inputType === 'number')
                                            <span class="mt-1 inline-flex rounded-full border border-slate-300 bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $item->unit }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($inputType === 'select')
                                            <select
                                                name="inventory_val[{{ $item->id }}]"
                                                id="inv-{{ $item->id }}"
                                                class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm @error('inventory_val.'.$item->id) ring-2 ring-rose-300 @enderror"
                                                aria-label="{{ $item->name }}"
                                            >
                                                <option value="">選択</option>
                                                @foreach ($item->options ?? [] as $opt)
                                                    <option
                                                        value="{{ $opt }}"
                                                        @selected(old('inventory_val.'.$item->id, $inventoryValues[$item->id] ?? '') == $opt)
                                                    >{{ $opt }}</option>
                                                @endforeach
                                            </select>
                                            @error('inventory_val.'.$item->id)
                                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                                            @enderror
                                        @elseif ($inputType === 'text')
                                            <input
                                                type="text"
                                                name="inventory_val[{{ $item->id }}]"
                                                id="inv-{{ $item->id }}"
                                                value="{{ old('inventory_val.'.$item->id, $inventoryValues[$item->id] ?? '') }}"
                                                class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm @error('inventory_val.'.$item->id) ring-2 ring-rose-300 @enderror"
                                                aria-label="{{ $item->name }}"
                                            >
                                            @error('inventory_val.'.$item->id)
                                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                                            @enderror
                                        @else
                                            <input
                                                type="number"
                                                name="inventory_val[{{ $item->id }}]"
                                                id="inv-{{ $item->id }}"
                                                step="0.01"
                                                min="0"
                                                inputmode="decimal"
                                                value="{{ old('inventory_val.'.$item->id, $inventoryValues[$item->id] ?? '') }}"
                                                class="block w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-right text-sm @error('inventory_val.'.$item->id) ring-2 ring-rose-300 @enderror"
                                                placeholder="0"
                                                aria-label="{{ $item->name }}"
                                            >
                                            @error('inventory_val.'.$item->id)
                                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                                            @enderror
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
            </div>

            <div class="mb-4 rounded-xl border border-slate-300 bg-white p-4 shadow-sm">
                    <label for="pin_code" class="mb-1 block text-sm font-semibold text-slate-700">保存用 PIN（4桁）</label>
                    <input
                        type="password"
                        name="pin_code"
                        id="pin_code"
                        inputmode="numeric"
                        maxlength="4"
                        required
                        class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono text-lg"
                        placeholder="••••"
                        autocomplete="one-time-code"
                    >
            </div>

            <div class="grid gap-2 pb-5 sm:grid-cols-2">
                <button type="submit" class="rounded-lg border-2 border-black bg-indigo-600 px-4 py-3 text-base font-black text-white">保存</button>
                <a href="{{ route('inventory.index', ['staff_id' => $staff->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-3 text-center text-base font-semibold text-slate-700">全体表に戻る</a>
            </div>
        </form>
    </div>
</body>
</html>
