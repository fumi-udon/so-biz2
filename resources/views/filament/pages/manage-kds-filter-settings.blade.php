<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                {{ __('filament.kds_filter.save') }}
            </x-filament::button>
        </div>
    </form>

    @if ($dictionaryDraftModalOpen)
        @teleport('body')
            <div
                class="fixed inset-0 z-[90] flex items-center justify-center bg-black/50 p-4 dark:bg-black/60"
                wire:click="closeDictionaryDraft"
                wire:key="kds-dictionary-draft-overlay"
            >
                <div
                    class="fi-modal-window w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    wire:click.stop
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="kds-dictionary-draft-heading"
                >
                    <div class="max-h-[min(85dvh,32rem)] overflow-y-auto p-6">
                        <h2
                            id="kds-dictionary-draft-heading"
                            class="text-base font-semibold text-gray-950 dark:text-white"
                        >
                            KDS 変換辞書 — 下書き
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            登録マスタの Style / Topping 名から「元の名前:略称」を生成しました（略称は目安6文字程度）。必要に応じて編集し、下のコピーから辞書欄へ貼り付けてください。
                        </p>
                        <label class="sr-only" for="kds-dictionary-draft-body">下書きテキスト</label>
                        <textarea
                            id="kds-dictionary-draft-body"
                            readonly
                            rows="18"
                            class="mt-3 w-full rounded-lg border border-gray-300 bg-gray-50 font-mono text-xs text-gray-950 dark:border-gray-600 dark:bg-gray-950 dark:text-gray-100"
                        >{{ $dictionaryDraftText }}</textarea>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button
                                type="button"
                                wire:click="copyDictionaryDraftToClipboard"
                                color="primary"
                            >
                                クリップボードにコピー
                            </x-filament::button>
                            <x-filament::button
                                type="button"
                                wire:click="closeDictionaryDraft"
                                color="gray"
                            >
                                閉じる
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</x-filament-panels::page>
