<div>
    @if ($showSessionGate)
        @include('daily-close.partials.session-gate')
    @endif

    @include('daily-close.partials.result-overlay')

    @if ($bannerError !== '')
        <div class="mb-3 rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-600/40 dark:bg-rose-950/30 dark:text-rose-200" role="alert">{{ $bannerError }}</div>
    @endif
    @if ($bannerSuccess !== '')
        <div class="mb-3 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-600/40 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">{{ $bannerSuccess }}</div>
    @endif

    @if ($closeSessionReady && ! $showSessionGate)
        <!-- @include('daily-close.partials.stage-header') -->
        @include('daily-close.partials.recettes-strip')
        @include('daily-close.partials.form-fields')
        @include('daily-close.partials.history')
    @endif
</div>
