<div class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div x-data="{ open: false }" class="space-y-3">
        <button
            type="button"
            class="inline-flex items-center gap-2 text-sm font-semibold text-primary-600 underline underline-offset-2 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
            x-on:click="open = !open"
            x-bind:aria-expanded="open.toString()"
        >
            <x-filament::icon icon="heroicon-o-book-open" class="h-4 w-4" />
            <span x-show="!open">仕様を表示（キー名マッピング）</span>
            <span x-show="open">仕様を閉じる（キー名マッピング）</span>
        </button>

        <div x-show="open" x-collapse class="space-y-3">
            <p class="text-xs text-gray-600 dark:text-gray-400">
                設定は「キー」「値（JSON）」「説明」で登録します。レジ締めで使用するキーは次の3つです。
            </p>

            <div class="overflow-auto rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                <table class="w-full min-w-[42rem] text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300">機能</th>
                            <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300">キー名</th>
                            <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300">値の例</th>
                            <th class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-300">説明（推奨）</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                        <tr>
                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">Fond de caisse（表示専用）</td>
                            <td class="px-3 py-2 font-mono text-primary-700 dark:text-primary-300">fond_de_caisse</td>
                            <td class="px-3 py-2 font-mono text-gray-800 dark:text-gray-200">100.000</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">Fond de caisse (DT)</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">Tolérance moins（判定）</td>
                            <td class="px-3 py-2 font-mono text-primary-700 dark:text-primary-300">tolerance_moins</td>
                            <td class="px-3 py-2 font-mono text-gray-800 dark:text-gray-200">1.000</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">Tolérance Moins (DT)</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">Tolérance plus（判定）</td>
                            <td class="px-3 py-2 font-mono text-primary-700 dark:text-primary-300">tolerance_plus</td>
                            <td class="px-3 py-2 font-mono text-gray-800 dark:text-gray-200">3.000</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">Tolérance Plus (DT)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

