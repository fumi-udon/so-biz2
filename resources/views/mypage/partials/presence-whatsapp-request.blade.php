{{--
    Variables expected from parent (mypage.index):
    $staff : Staff
--}}
<div
    x-data="{
        message: '',
        errorOpen: false,
        staffName: @js($staff->name ?? ''),
        sendWhatsapp() {
            if (this.message.trim() === '') {
                this.errorOpen = true;
                return;
            }
            const text = `[Demande de correction: ${this.staffName}]\n\n${this.message}`;
            window.open(`https://wa.me/21651992184?text=${encodeURIComponent(text)}`, '_blank');
        },
    }"
    class="mt-3 rounded-2xl border-2 border-black bg-white p-4 shadow-[0_6px_0_0_rgba(0,0,0,1)] dark:border-slate-600/80 dark:bg-slate-900"
>
    <label class="mb-2 block text-sm font-black text-emerald-900 dark:text-emerald-200">
        💬 Demande de correction au manager (WhatsApp)
    </label>
    <textarea
        x-model="message"
        rows="3"
        class="block w-full rounded-lg border border-emerald-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 dark:border-emerald-600/40 dark:bg-slate-800 dark:text-slate-100"
        placeholder="Ex.: J'ai oublie de pointer la sortie du diner du 12/04. Sortie a 23:30."
    ></textarea>
    <button
        type="button"
        class="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-4 py-2 text-sm font-bold text-white transition hover:bg-green-600 active:scale-95"
        @click="sendWhatsapp()"
    >
        <span>💬</span>
        <span>Envoyer au manager via WhatsApp</span>
    </button>

    {{-- Erreur: champ vide --}}
    <div
        x-show="errorOpen"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4"
        @click.self="errorOpen = false"
    >
        <div class="w-full max-w-sm rounded-xl border border-rose-200 bg-white p-4 shadow-xl dark:border-rose-600/40 dark:bg-slate-900">
            <h3 class="text-base font-bold text-rose-700 dark:text-rose-300">Erreur de saisie</h3>
            <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">Veuillez saisir le contenu de la correction avant l'envoi.</p>
            <button
                type="button"
                class="mt-4 inline-flex w-full items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                @click="errorOpen = false"
            >Fermer</button>
        </div>
    </div>
</div>
