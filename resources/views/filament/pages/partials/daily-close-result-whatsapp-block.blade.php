@php
    $waJsPayload = [
        'appName' => config('app.name'),
        'businessDate' => $this->businessDateStr,
        'shiftFr' => $this->closeReportShiftLabelFr(),
        'resultLine' => $this->closeReportResultLine(),
        'operator' => \Filament\Facades\Filament::auth()->user()?->name ?? '—',
        'responsible' => $this->responsibleStaffDisplayName(),
    ];
@endphp

<div
    x-data="{
        hasReported: false,
        reportComment: '',
        waConfigured: @js($this->whatsappManagerConfigured()),
        waDigits: @js($this->whatsappManagerNumberDigits()),
        payload: @js($waJsPayload),
        sendWhatsapp() {
            if (!this.waConfigured || !this.waDigits || this.hasReported) {
                return;
            }
            const comment = this.reportComment.trim() === '' ? 'Aucun' : this.reportComment.trim();
            const p = this.payload;
            const text = '[Rapport de Clôture - ' + p.appName + ']\n'
                + 'Date : ' + p.businessDate + '\n'
                + 'Shift : ' + p.shiftFr + '\n'
                + 'Résultat : ' + p.resultLine + '\n'
                + 'Opérateur : ' + p.operator + '\n'
                + 'Responsable (PIN) : ' + p.responsible + '\n'
                + 'Commentaire : ' + comment;
            window.open('https://wa.me/' + this.waDigits + '?text=' + encodeURIComponent(text), '_blank');
            this.hasReported = true;
            $wire.markCloseWhatsappReportDone();
        },
    }"
    class="w-full space-y-3"
>
    <div class="rounded-xl border-2 border-emerald-600/50 bg-gradient-to-br from-emerald-50 to-teal-50 p-4 shadow-sm ring-1 ring-emerald-500/20 dark:border-emerald-500/40 dark:from-emerald-950/50 dark:to-teal-950/40 dark:ring-emerald-400/15">
        <p class="text-center text-base font-black text-emerald-900 dark:text-emerald-100">
            Rapport de Clôture effectué !
        </p>
        <p class="mt-1 text-center text-xs font-medium text-emerald-800/90 dark:text-emerald-200/90">
            Envoyez le récapitulatif au patron via WhatsApp pour finaliser.
        </p>

        <p
            x-show="!waConfigured"
            x-cloak
            class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-center text-xs font-semibold text-amber-950 dark:border-amber-600/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
            Définissez <span class="font-mono">WHATSAPP_MANAGER_NUMBER</span> dans <span class="font-mono">.env</span> (config/services) pour activer l’envoi.
        </p>

        <div class="mt-3">
            <label class="mb-1 block text-xs font-bold text-gray-950 dark:text-white" for="daily-close-wa-comment">
                Commentaire (optionnel)
            </label>
            <textarea
                id="daily-close-wa-comment"
                x-model="reportComment"
                rows="3"
                class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none transition duration-75 placeholder:text-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-inset focus:ring-primary-500 disabled:bg-gray-50 disabled:text-gray-500 disabled:opacity-70 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-primary-500 dark:disabled:bg-transparent"
                placeholder="Notes pour le patron…"
            ></textarea>
        </div>

        <button
            type="button"
            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-black uppercase tracking-wide text-white shadow-md transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
            style="background-color: #25D366;"
            x-bind:disabled="!waConfigured || !waDigits || hasReported"
            x-on:click="sendWhatsapp()"
        >
            <span aria-hidden="true">💬</span>
            <span>Envoyer sur WhatsApp</span>
        </button>
    </div>

    @if (! $this->whatsappManagerConfigured() || $closeWaReportDone)
        <x-filament::button
            type="button"
            color="{{ $fermerColor }}"
            class="w-full text-gray-950 dark:text-white"
            x-on:click="$dispatch('close-modal', { id: 'daily-close-result-modal' })"
        >
            {{ $fermerLabel }}
        </x-filament::button>
    @endif
</div>
