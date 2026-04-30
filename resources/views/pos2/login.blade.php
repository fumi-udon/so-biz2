<!DOCTYPE html>
<html lang="ja" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POS2 PIN Login</title>
    @vite(['resources/css/app.css'])
    <style>
        @keyframes pos2-shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-10px); }
            40% { transform: translateX(10px); }
            60% { transform: translateX(-8px); }
            80% { transform: translateX(8px); }
        }
        .pos2-shake {
            animation: pos2-shake .36s ease;
        }
    </style>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-5 py-8">
        <section id="pin-card" class="rounded-3xl border border-slate-700/70 bg-slate-900/80 p-5 shadow-2xl shadow-black/30">
            <header class="mb-4">
                <p class="text-xs font-medium tracking-[0.2em] text-slate-400">SÖYA POS2</p>
                <h1 class="mt-2 text-xl font-semibold text-slate-100">PINログイン</h1>
                <p class="mt-1 text-sm text-slate-400">4桁入力で自動ログインします</p>
            </header>

            @if ($errors->has('pin'))
                <div class="mb-4 rounded-xl border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">
                    {{ $errors->first('pin') }}
                </div>
            @endif

            <div id="pin-display" class="mb-5 grid grid-cols-4 gap-2 rounded-2xl border border-slate-700 bg-slate-950/70 p-4 text-center text-2xl font-bold tracking-[0.3em] text-slate-200">
                <span data-slot>*</span>
                <span data-slot>*</span>
                <span data-slot>*</span>
                <span data-slot>*</span>
            </div>

            <form id="pin-form" method="POST" action="{{ route('pos2.login.submit') }}" class="hidden">
                @csrf
                <input id="pin-input" type="password" name="pin" inputmode="numeric" autocomplete="one-time-code" maxlength="4">
            </form>

            <div class="grid grid-cols-3 gap-3">
                @foreach ([1,2,3,4,5,6,7,8,9] as $num)
                    <button type="button" data-key="{{ $num }}"
                        class="flex h-16 items-center justify-center rounded-2xl border border-slate-700 bg-slate-800 text-2xl font-semibold text-slate-100 active:scale-[0.98] active:bg-slate-700">
                        {{ $num }}
                    </button>
                @endforeach
                <button type="button" data-action="clear"
                    class="flex h-16 items-center justify-center rounded-2xl border border-rose-600/70 bg-rose-900/40 text-base font-semibold text-rose-100 active:scale-[0.98] active:bg-rose-800/60">
                    削除
                </button>
                <button type="button" data-key="0"
                    class="flex h-16 items-center justify-center rounded-2xl border border-slate-700 bg-slate-800 text-2xl font-semibold text-slate-100 active:scale-[0.98] active:bg-slate-700">
                    0
                </button>
                <button type="button" data-action="reset"
                    class="flex h-16 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900/70 text-sm font-semibold text-slate-300 active:scale-[0.98] active:bg-slate-800">
                    クリア
                </button>
            </div>
        </section>
    </main>

    <script>
        (() => {
            const form = document.getElementById('pin-form');
            const pinInput = document.getElementById('pin-input');
            const slots = Array.from(document.querySelectorAll('[data-slot]'));
            const card = document.getElementById('pin-card');
            const state = [];

            const rerender = () => {
                slots.forEach((slot, index) => {
                    slot.textContent = state[index] ? '●' : '*';
                });
                pinInput.value = state.join('');
            };

            const submitIfReady = () => {
                if (state.length === 4) {
                    form.submit();
                }
            };

            document.addEventListener('click', (event) => {
                const target = event.target.closest('button');
                if (!target) return;

                const key = target.getAttribute('data-key');
                const action = target.getAttribute('data-action');

                if (key !== null) {
                    if (state.length >= 4) return;
                    state.push(key);
                    rerender();
                    submitIfReady();
                    return;
                }

                if (action === 'clear') {
                    state.pop();
                    rerender();
                    return;
                }

                if (action === 'reset') {
                    state.length = 0;
                    rerender();
                }
            });

            @if ($errors->has('pin'))
                card.classList.add('pos2-shake');
                setTimeout(() => card.classList.remove('pos2-shake'), 450);
            @endif

            rerender();
        })();
    </script>
</body>
</html>
