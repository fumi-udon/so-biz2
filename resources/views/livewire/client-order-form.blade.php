<div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm">
    <h1 class="text-xl font-semibold text-slate-800">
        {{ __('テーブル') }} <span class="text-amber-700">{{ $table_number }}</span>
    </h1>
    <p class="mt-1 text-sm text-slate-500">
        {{ __('ご注文内容を入力して送信してください。') }}
    </p>

    @if ($submitted)
        <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ __('注文を受け付けました。スタッフが確認します。') }}
        </div>
    @endif

    <form wire:submit="submit" class="mt-6 space-y-4">
        <div>
            <label for="items" class="block text-sm font-medium text-slate-700">
                {{ __('注文内容') }}
            </label>
            <textarea
                id="items"
                wire:model="items"
                rows="6"
                class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/30"
                placeholder="{{ __('例: ハンバーガー×2、コーラ×1 …') }}"
            ></textarea>
            @error('items')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-lg bg-amber-600 px-4 py-3 text-sm font-semibold text-white shadow hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove wire:target="submit">{{ __('注文を送信') }}</span>
            <span wire:loading wire:target="submit">{{ __('送信中…') }}</span>
        </button>
    </form>
</div>
