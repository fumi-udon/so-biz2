@php
    $verdict = $resultModalCalc['verdict'] ?? 'minus_error';
    $isPlusErr = $resultModalKind !== 'bravo' && $verdict === 'plus_error';
    $isMinusErr = $resultModalKind !== 'bravo' && ! $isPlusErr;
@endphp
<div
    x-data="{ show: @entangle('showResultOverlay') }"
    x-show="show"
    x-cloak
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4 backdrop-blur-[2px] dark:bg-black/50"
    role="dialog"
    aria-modal="true"
    style="display: none;"
>
    <div
        @class([
            'relative w-full max-w-md overflow-hidden rounded-2xl border-2 p-6 text-center shadow-2xl',
            'border-emerald-400/80 bg-gradient-to-br from-emerald-50 via-white to-teal-100 ring-4 ring-emerald-400/40 dark:border-emerald-500/50 dark:from-emerald-950/60 dark:via-gray-800 dark:to-teal-950/50 dark:ring-emerald-500/25' => $resultModalKind === 'bravo',
            'border-rose-400/90 bg-gradient-to-br from-rose-950 via-rose-950/95 to-zinc-950 ring-2 ring-rose-500/35 dark:border-rose-500/70 dark:from-rose-950 dark:via-rose-950 dark:to-black dark:ring-rose-400/25' => $isMinusErr,
            'border-amber-400/90 bg-gradient-to-br from-amber-950 via-amber-950/95 to-zinc-950 ring-2 ring-amber-500/35 dark:border-amber-500/70 dark:from-amber-950 dark:via-amber-950 dark:to-black dark:ring-amber-400/25' => $isPlusErr,
        ])
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        @click.stop
    >
        @if ($resultModalKind === 'bravo')
            <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-emerald-400/25 blur-2xl dark:bg-emerald-500/20"></div>
            <div class="pointer-events-none absolute -bottom-4 -left-4 h-24 w-24 rounded-full bg-teal-400/20 blur-2xl dark:bg-teal-500/15"></div>
            <div class="relative mb-4 flex flex-col items-center gap-2">
                <p class="text-3xl font-black tracking-tight text-emerald-600 drop-shadow-sm dark:text-emerald-300 sm:text-4xl">
                    Bravo !!
                </p>
                <p class="flex items-center gap-1.5 text-sm font-semibold text-emerald-800 dark:text-emerald-200">
                    <span class="text-lg" aria-hidden="true">✨</span>
                    <span class="rounded-full border border-emerald-300/80 bg-white/90 px-4 py-1.5 text-base font-bold text-emerald-900 shadow-sm dark:border-emerald-500/40 dark:bg-emerald-900/40 dark:text-emerald-100">
                        {{ $this->responsibleStaffDisplayName() }}
                    </span>
                    <span class="text-lg" aria-hidden="true">✨</span>
                </p>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Clôture validée</p>
            </div>
        @else
            <div class="pointer-events-none absolute -right-5 -top-5 h-24 w-24 rounded-full blur-2xl {{ $isPlusErr ? 'bg-amber-400/25 dark:bg-amber-400/20' : 'bg-rose-400/25 dark:bg-rose-400/20' }}"></div>
            <div class="pointer-events-none absolute -bottom-4 -left-4 h-20 w-20 rounded-full blur-2xl {{ $isPlusErr ? 'bg-amber-500/15' : 'bg-rose-500/15' }}"></div>
            <div class="relative mb-4 flex flex-col items-center gap-3">
                @if ($isMinusErr)
                    <div class="w-full rounded-xl border-2 border-rose-400 bg-rose-900/70 p-4 text-gray-950 shadow-inner ring-1 ring-rose-300/25 dark:bg-rose-950/80 dark:text-white">
                        <p class="text-sm font-bold tracking-wide text-rose-100">[ ERR : INSUFFISANCE ]</p>
                        <p class="mt-3 flex items-center justify-center gap-3 font-mono text-2xl font-bold uppercase tracking-wider text-rose-50">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-600/60 text-3xl font-black leading-none text-white shadow-inner ring-1 ring-rose-300/40" aria-hidden="true">－</span>
                            <span>{{ number_format((float) ($resultModalCalc['final_difference'] ?? 0), 3, '.', ',') }} DT</span>
                        </p>
                    </div>
                @else
                    <div class="w-full rounded-xl border-2 border-amber-400 bg-amber-900/70 p-4 text-gray-950 shadow-inner ring-1 ring-amber-300/25 dark:bg-amber-950/80 dark:text-white">
                        <p class="text-sm font-bold tracking-wide text-amber-100">[ ERR : SURCHARGE ]</p>
                        <p class="mt-3 flex items-center justify-center gap-3 font-mono text-2xl font-bold uppercase tracking-wider text-amber-50">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-amber-600/60 text-3xl font-black leading-none text-white shadow-inner ring-1 ring-amber-300/40" aria-hidden="true">＋</span>
                            <span>{{ number_format((float) ($resultModalCalc['final_difference'] ?? 0), 3, '.', ',') }} DT</span>
                        </p>
                    </div>
                @endif
            </div>
        @endif

        <p @class([
            'mb-4 text-sm font-semibold',
            'text-gray-800 dark:text-gray-100' => $resultModalKind === 'bravo',
            'text-rose-100' => $isMinusErr,
            'text-amber-100' => $isPlusErr,
        ])>{{ $resultModalShiftLabel }}</p>

        <dl @class([
            'mb-5 space-y-2 rounded-xl border p-3 text-left text-xs shadow-inner',
            'border-emerald-200/80 bg-white/70 dark:border-emerald-700/40 dark:bg-gray-900/40' => $resultModalKind === 'bravo',
            'border-rose-400/70 bg-rose-900/40 dark:border-rose-500/50 dark:bg-rose-950/60' => $isMinusErr,
            'border-amber-400/70 bg-amber-900/40 dark:border-amber-500/50 dark:bg-amber-950/60' => $isPlusErr,
        ])>
            @if ($resultModalKind !== 'bravo')
                <div @class([
                    'flex justify-between gap-2 rounded-lg px-2 py-2',
                    'bg-rose-950/70 dark:bg-rose-900/50' => $isMinusErr,
                    'bg-amber-950/70 dark:bg-amber-900/50' => $isPlusErr,
                ])>
                    <dt @class(['text-sm font-semibold', 'text-rose-200' => $isMinusErr, 'text-amber-200' => $isPlusErr])>Caisse</dt>
                    <dd @class(['font-mono text-sm font-bold tabular-nums', 'text-rose-50' => $isMinusErr, 'text-amber-50' => $isPlusErr])>{{ number_format((float) ($resultModalCalc['measured_without_declared_tip'] ?? 0), 3, '.', ',') }}</dd>
                </div>
            @endif
            <div @class([
                'flex justify-between gap-2 rounded-lg px-2 py-2',
                'bg-rose-950/70 dark:bg-rose-900/50' => $isMinusErr,
                'bg-amber-950/70 dark:bg-amber-900/50' => $isPlusErr,
            ])>
                <dt @class([
                    'text-sm font-semibold',
                    'text-gray-500 dark:text-gray-400' => $resultModalKind === 'bravo',
                    'text-rose-200' => $isMinusErr,
                    'text-amber-200' => $isPlusErr,
                ])>Recettes ＋ Pourboire</dt>
                <dd @class([
                    'font-mono text-sm font-bold tabular-nums',
                    'text-gray-900 dark:text-gray-100' => $resultModalKind === 'bravo',
                    'text-rose-50' => $isMinusErr,
                    'text-amber-50' => $isPlusErr,
                ])>{{ number_format((float) ($resultModalCalc['sum_tip_plus_pos_sales'] ?? 0), 3, '.', ',') }}</dd>
            </div>
        </dl>

        @if ($resultModalKind === 'bravo')
            <a
                href="{{ url('/') }}"
                @class([
                    'w-full inline-block text-center rounded-xl px-4 py-3 text-sm font-bold text-white shadow-[0_5px_0_0_rgba(0,0,0,0.25)] transition hover:brightness-110 active:translate-y-1 active:shadow-[0_2px_0_0_rgba(0,0,0,0.2)]',
                    'bg-gradient-to-b from-emerald-500 to-teal-700 ring-2 ring-emerald-400/50 hover:from-emerald-400 hover:to-teal-600 dark:from-emerald-600 dark:to-teal-800',
                ])
            >
                Retour à la page d'accueil
            </a>
        @else
            <button
                type="button"
                @click="show = false"
                @class([
                    'w-full rounded-xl px-4 py-3 text-sm font-bold shadow-[0_5px_0_0_rgba(0,0,0,0.35)] border-b-4 transition hover:brightness-110 active:translate-y-1 active:border-b-0 active:shadow-[0_2px_0_0_rgba(0,0,0,0.25)]',
                    'border border-rose-500 bg-rose-900 text-rose-50 border-b-rose-950 ring-1 ring-rose-400/30 dark:border-rose-400 dark:bg-rose-950 dark:text-rose-100' => $isMinusErr,
                    'border border-amber-500 bg-amber-900 text-amber-50 border-b-amber-950 ring-1 ring-amber-400/30 dark:border-amber-400 dark:bg-amber-950 dark:text-amber-100' => $isPlusErr,
                ])
                aria-label="Corriger les valeurs et fermer l’overlay"
            >
                CORRIGER LES VALEURS (やり直す)
            </button>
        @endif
    </div>
</div>
