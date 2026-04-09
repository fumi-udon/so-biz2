@extends('layouts.app')

@section('title', 'Clôture des tâches — '.config('app.name', 'Laravel'))

@section('content')
<div class="min-h-screen bg-zinc-100 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <x-client-nav />

    @php
        $hasClockoutWarnings = count($clockoutWarnings) > 0;
    @endphp

    <div
        class="mx-auto flex min-h-[70vh] w-full max-w-3xl flex-col items-center justify-center px-3 py-6 sm:px-4"
        x-data="{
            showClockoutModal: @json($hasClockoutWarnings),
            showWhatsappBlock: @json(! $hasClockoutWarnings),
            hasReported: false,
            reportComment: '',
            waDigits: @js($whatsappDigits),
            waConfigured: @json($whatsappDigits !== ''),
            appName: @js(config('app.name')),
            businessDate: @js($businessDate),
            responsible: @js($closedStaffName),
            dismissClockout() {
                this.showClockoutModal = false;
                this.showWhatsappBlock = true;
            },
            sendWhatsapp() {
                if (!this.waConfigured || !this.waDigits || this.hasReported) {
                    return;
                }
                const comment = this.reportComment.trim() === '' ? 'Aucun' : this.reportComment.trim();
                const text = '[Rapport de Clôture des Tâches - ' + this.appName + ']\n'
                    + 'Date : ' + this.businessDate + '\n'
                    + 'Responsable : ' + this.responsible + '\n'
                    + 'Tâches : Terminées \n'
                    + 'Commentaire : ' + comment;
                window.open('https://wa.me/' + this.waDigits + '?text=' + encodeURIComponent(text), '_blank');
                this.hasReported = true;
            },
            canGoHome() {
                return this.hasReported || !this.waConfigured;
            },
        }"
    >
        {{-- Étape A : oubli de pointage (sortie) — pas de fermeture au clic extérieur --}}
        <div
            x-show="showClockoutModal"
            x-cloak
            class="fixed inset-0 z-[60] flex items-center justify-center bg-zinc-950/90 p-4 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="clockout-modal-title"
        >
            <div class="w-full max-w-lg rounded-lg border border-zinc-700 bg-zinc-900 p-4 text-zinc-100 shadow-inner">
                <p class="text-center font-mono text-[10px] uppercase tracking-widest text-amber-400 sm:text-xs">Système — attention</p>
                <h2 id="clockout-modal-title" class="mt-2 text-center font-mono text-lg font-bold uppercase tracking-widest text-amber-300">
                    Oubli de pointage
                </h2>
                <p class="mt-4 text-center text-sm font-medium leading-relaxed text-zinc-300">
                    Veuillez vérifier les pointages de sortie pour les employés suivants :
                </p>
                <ul class="mt-4 max-h-48 list-disc space-y-1 overflow-y-auto rounded border border-zinc-700 bg-zinc-950 px-5 py-3 text-sm text-zinc-100">
                    @foreach ($clockoutWarnings as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ul>
                <button
                    type="button"
                    class="mt-6 w-full rounded-lg border border-amber-600 bg-amber-600 px-4 py-3 text-center font-mono text-sm font-bold uppercase tracking-widest text-zinc-950 border-b-4 border-b-amber-900 transition hover:brightness-105 active:border-b-0 active:translate-y-1"
                    @click="dismissClockout()"
                >
                    Fermer
                </button>
            </div>
        </div>

        {{-- Carte de remerciement (toujours visible en arrière-plan) --}}
        <div class="w-full rounded-lg border border-zinc-700 bg-zinc-900 p-6 text-center shadow-inner dark:border-zinc-600">
            <p class="font-mono text-[10px] uppercase tracking-widest text-emerald-400 sm:text-xs">Session — terminée</p>
            <p class="mt-3 font-mono text-lg font-bold uppercase tracking-widest text-emerald-300 sm:text-xl">
                Merci!
            </p>
            <p class="mt-4 font-mono text-2xl font-bold uppercase tracking-wider text-zinc-100 sm:text-3xl">{{ $closedStaffName }}</p>
        </div>

        {{-- Étape B : rapport WhatsApp --}}
        <div
            x-show="showWhatsappBlock"
            x-cloak
            class="mt-6 w-full"
        >
            <div class="rounded-lg border border-zinc-700 bg-zinc-900 p-4 shadow-inner dark:border-zinc-600">
                <p class="text-center font-mono text-xs font-bold uppercase tracking-widest text-emerald-400 sm:text-sm">
                    Rapport au responsable
                </p>
                <p class="mt-1 text-center text-xs font-medium text-zinc-400">
                    Envoyez le récapitulatif via WhatsApp pour terminer la procédure.
                </p>

                <p
                    x-show="!waConfigured"
                    x-cloak
                    class="mt-3 rounded border border-amber-700/60 bg-amber-950/40 px-3 py-2 text-center text-xs font-semibold text-amber-100"
                >
                    Numéro WhatsApp du responsable non configuré. Vous pouvez retourner à l’accueil. Demandez à l’administrateur de définir <span class="font-mono">WHATSAPP_MANAGER_NUMBER</span> dans la configuration.
                </p>

                <div class="mt-4">
                    <label class="mb-1 block font-mono text-xs font-bold uppercase tracking-wider text-zinc-300" for="close-check-wa-comment">
                        Commentaire (optionnel)
                    </label>
                    <textarea
                        id="close-check-wa-comment"
                        x-model="reportComment"
                        rows="3"
                        class="block w-full rounded border border-zinc-600 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 shadow-inner outline-none placeholder:text-zinc-500 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-500/40 disabled:opacity-60"
                        placeholder="Notes pour le responsable…"
                        :disabled="!waConfigured || hasReported"
                    ></textarea>
                </div>

                <button
                    type="button"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-600 bg-emerald-700 px-4 py-3 font-mono text-xs font-bold uppercase tracking-widest text-white border-b-4 border-b-emerald-950 transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:translate-y-0 enabled:active:border-b-0 enabled:active:translate-y-1 dark:border-emerald-500 dark:bg-emerald-600"
                    :disabled="!waConfigured || !waDigits || hasReported"
                    @click="sendWhatsapp()"
                >
                    <span aria-hidden="true">💬</span>
                    <span>Envoyer le rapport via WhatsApp</span>
                </button>

                <p
                    x-show="hasReported && waConfigured"
                    x-cloak
                    class="mt-3 text-center font-mono text-sm font-bold uppercase tracking-wide text-emerald-400"
                >
                    Rapport envoyé avec succès.
                </p>
            </div>
        </div>

        <a
            href="{{ route('home') }}"
            class="mt-8 inline-flex items-center justify-center rounded-lg border border-zinc-700 bg-zinc-800 px-6 py-2.5 font-mono text-sm font-bold uppercase tracking-wider text-zinc-100 border-b-4 border-b-black transition hover:brightness-105 active:border-b-0 active:translate-y-1 dark:border-zinc-600"
            :class="{ 'pointer-events-none opacity-40': !canGoHome() }"
            :aria-disabled="!canGoHome() ? 'true' : 'false'"
            @click="if (! canGoHome()) { $event.preventDefault() }"
        >
            Retour à l’accueil
        </a>
    </div>
</div>
@endsection
