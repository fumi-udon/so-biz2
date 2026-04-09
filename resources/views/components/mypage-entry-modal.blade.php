{{-- 名前選択 → PIN（同一PINの複数人に対応）— Tailwind + Alpine（Bootstrap 不使用） --}}
@props([
    'buttonClass' => 'rounded-lg border-2 border-black bg-emerald-400 px-3 py-2 text-sm font-black text-gray-900 shadow-[0_4px_0_0_rgba(0,0,0,1)] hover:bg-emerald-300 active:translate-y-0.5 active:shadow-none dark:bg-emerald-500 dark:text-gray-950',
])

<div class="inline-block text-gray-900 dark:text-gray-100" x-data="{ open: false }">
    <button type="button" @click="open = true" class="{{ $buttonClass }}">
        マイページを開く
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-[200] flex items-center justify-center bg-black/70 p-3"
        @click.self="open = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mypage-pin-modal-title"
    >
        <div
            class="w-full max-w-sm rounded-2xl border-4 border-black bg-white p-4 text-gray-900 shadow-[0_10px_0_0_rgba(0,0,0,1)] dark:bg-gray-950 dark:text-gray-100"
            @click.stop
        >
            <div class="mb-3 flex items-start justify-between gap-2">
                <h2 id="mypage-pin-modal-title" class="text-base font-black text-gray-900 dark:text-white">マイページを開く</h2>
                <button
                    type="button"
                    class="rounded-md border border-gray-300 bg-gray-100 px-2 py-1 text-xs font-bold text-gray-700 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="open = false"
                >
                    閉じる
                </button>
            </div>

            <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">名前を選んでから、4桁PINを入力してください。</p>

            <form method="post" action="{{ route('mypage.open') }}" class="space-y-2">
                @csrf
                <div>
                    <label for="mypage_modal_staff_id" class="mb-1 block text-xs font-bold text-gray-800 dark:text-gray-200">名前</label>
                    <div class="relative">
                        <select
                            name="staff_id"
                            id="mypage_modal_staff_id"
                            required
                            class="block w-full appearance-none rounded-lg border-2 border-gray-300 bg-white px-3 py-2.5 pr-9 text-sm font-semibold text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        >
                            <option value="" disabled selected>— 選択 —</option>
                            @foreach ($mypageStaffList as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-gray-600 dark:text-gray-400">▾</span>
                    </div>
                    @if ($mypageStaffList->isEmpty())
                        <p class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">アクティブなスタッフがありません。</p>
                    @endif
                </div>
                <div>
                    <label for="mypage_modal_pin" class="mb-1 block text-xs font-bold text-gray-800 dark:text-gray-200">PIN（4桁）</label>
                    <input
                        type="password"
                        name="pin_code"
                        id="mypage_modal_pin"
                        inputmode="numeric"
                        maxlength="4"
                        required
                        autocomplete="one-time-code"
                        placeholder="••••"
                        @disabled($mypageStaffList->isEmpty())
                        class="block w-full rounded-lg border-2 border-gray-300 bg-white px-3 py-2.5 text-center font-mono text-lg tracking-widest text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                    >
                </div>
                <button
                    type="submit"
                    @disabled($mypageStaffList->isEmpty())
                    class="mt-2 w-full rounded-lg border-2 border-black bg-emerald-500 py-2.5 text-sm font-black text-gray-950 shadow-[0_4px_0_0_rgba(0,0,0,1)] hover:bg-emerald-400 active:translate-y-0.5 active:shadow-none disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-500 dark:text-gray-950"
                >
                    開く
                </button>
            </form>
        </div>
    </div>
</div>
