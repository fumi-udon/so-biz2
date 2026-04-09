@extends('layouts.app')

@section('title', 'Clôture des tâches — '.config('app.name', 'Laravel'))

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
@endpush

@section('content')
<div class="min-h-screen bg-gradient-to-b from-sky-100 via-emerald-50 to-teal-100 text-gray-950 dark:from-slate-950 dark:via-emerald-950/40 dark:to-slate-900 dark:text-gray-100">
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
        {{-- Étape A : oubli de pointage --}}
        <div
            x-show="showClockoutModal"
            x-cloak
            class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="clockout-modal-title"
        >
            <div class="w-full max-w-lg rounded-2xl border-2 border-amber-500 bg-slate-900 p-4 text-slate-100 shadow-2xl ring-1 ring-amber-400/30">
                <h2 id="clockout-modal-title" class="text-center text-xl font-black text-amber-300">
                    Attention : Oubli de pointage
                </h2>
                <p class="mt-4 text-center text-sm font-medium leading-relaxed text-slate-200">
                    Veuillez vérifier les pointages de sortie pour les employés suivants :
                </p>
                <ul class="mt-4 max-h-48 list-disc space-y-1 overflow-y-auto rounded-lg border border-slate-600 bg-slate-950/80 px-5 py-3 text-sm text-white">
                    @foreach ($clockoutWarnings as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ul>
                <button
                    type="button"
                    class="mt-6 w-full rounded-xl border-2 border-amber-400 bg-gradient-to-b from-amber-400 to-amber-600 px-4 py-3 text-center text-base font-black text-slate-950 shadow-[0_4px_0_0_#92400e] transition hover:brightness-105 active:translate-y-0.5 active:shadow-[0_2px_0_0_#92400e]"
                    @click="dismissClockout()"
                >
                    Fermer
                </button>
            </div>
        </div>

        {{-- STAGE CLEAR --}}
        <div class="w-full rounded-2xl border-4 border-emerald-400/80 bg-gradient-to-br from-emerald-100 via-white to-cyan-100 p-6 text-center shadow-[0_10px_0_0_rgba(6,95,70,0.35)] ring-2 ring-emerald-300/50 dark:border-emerald-600/60 dark:from-emerald-950/80 dark:via-slate-900 dark:to-cyan-950/50 dark:shadow-[0_10px_0_0_rgba(6,78,59,0.5)] dark:ring-emerald-500/30">
            <p class="font-['Press_Start_2P'] text-[10px] leading-relaxed tracking-tight text-emerald-700 dark:text-emerald-300 sm:text-xs">
            MISSION ACCOMPLIE
            </p>
            <p class="mt-4 text-lg font-bold text-gray-950 dark:text-gray-100">
                Merci pour votre excellent travail!
            </p>
            <p class="mt-3 text-2xl font-black tracking-tight text-emerald-800 dark:text-emerald-300 sm:text-3xl">{{ $closedStaffName }}</p>
        </div>

        {{-- Rapport WhatsApp --}}
        <div
            x-show="showWhatsappBlock"
            x-cloak
            class="mt-6 w-full"
        >
            <div class="rounded-2xl border-2 border-emerald-600/70 bg-gradient-to-br from-emerald-50 to-teal-50 p-4 shadow-md ring-1 ring-emerald-500/25 dark:border-emerald-500/50 dark:from-emerald-950/60 dark:to-teal-950/50 dark:ring-emerald-400/20">
                <p class="text-center text-base font-black text-emerald-950 dark:text-emerald-100">
                    Rapport au responsable
                </p>
                <p class="mt-1 text-center text-xs font-medium text-emerald-900/90 dark:text-emerald-200/90">
                    Envoyez le récapitulatif via WhatsApp pour terminer la procédure.
                </p>

                <p
                    x-show="!waConfigured"
                    x-cloak
                    class="mt-3 rounded-lg border border-amber-400 bg-amber-50 px-3 py-2 text-center text-xs font-semibold text-amber-950 dark:border-amber-600/50 dark:bg-amber-950/50 dark:text-amber-100"
                >
                    Numéro WhatsApp du responsable non configuré. Vous pouvez retourner à l’accueil. Demandez à l’administrateur de définir <span class="font-mono">WHATSAPP_MANAGER_NUMBER</span> dans la configuration.
                </p>

                <div class="mt-4">
                    <label class="mb-1 block text-xs font-bold text-gray-950 dark:text-white" for="close-check-wa-comment">
                        Commentaire (optionnel)
                    </label>
                    <textarea
                        id="close-check-wa-comment"
                        x-model="reportComment"
                        rows="3"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 disabled:opacity-60 dark:border-white/15 dark:bg-white/10 dark:text-white dark:placeholder:text-gray-500"
                        placeholder="Notes pour le responsable…"
                        :disabled="!waConfigured || hasReported"
                    ></textarea>
                </div>

                <button
                    type="button"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-emerald-800 bg-gradient-to-b from-emerald-500 to-teal-700 px-4 py-3 text-sm font-black uppercase tracking-wide text-white shadow-[0_5px_0_0_#134e4a] transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50 active:translate-y-0.5 active:shadow-[0_2px_0_0_#134e4a]"
                    :disabled="!waConfigured || !waDigits || hasReported"
                    @click="sendWhatsapp()"
                >
                    <span aria-hidden="true">💬</span>
                    <span>Envoyer le rapport via WhatsApp</span>
                </button>

                <p
                    x-show="hasReported && waConfigured"
                    x-cloak
                    class="mt-3 text-center text-sm font-bold text-emerald-800 dark:text-emerald-300"
                >
                    Rapport envoyé avec succès.
                </p>
            </div>
        </div>

        <a
            href="{{ route('home') }}"
            class="mt-8 inline-flex items-center justify-center rounded-full border-2 border-emerald-800 bg-gradient-to-b from-white to-emerald-100 px-6 py-2.5 text-base font-bold text-emerald-950 shadow-[0_4px_0_0_rgba(6,78,59,0.45)] hover:brightness-105 active:translate-y-0.5 dark:border-emerald-600 dark:from-slate-800 dark:to-emerald-950 dark:text-emerald-100 dark:shadow-[0_4px_0_0_rgba(6,78,59,0.6)]"
            :class="{ 'pointer-events-none opacity-40': !canGoHome() }"
            :aria-disabled="!canGoHome() ? 'true' : 'false'"
            @click="if (! canGoHome()) { $event.preventDefault() }"
        >
            Retour à l’accueil
        </a>
    </div>
</div>
@endsection
