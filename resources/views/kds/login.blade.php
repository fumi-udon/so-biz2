@extends('layouts.kds')

@section('content')
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm rounded-2xl border border-slate-800 bg-slate-900/80 p-8 shadow-xl">
            <h1 class="text-center text-xl font-semibold tracking-wide text-slate-100">
                {{ __('kds.login_title') }}
            </h1>
            <p class="mt-1 text-center text-xs text-slate-400">
                {{ __('kds.login_subtitle') }}
            </p>

            <form method="post" action="{{ route('kds.login.submit') }}" class="mt-8 space-y-4">
                @csrf
                <div>
                    <label for="kds-pin" class="sr-only">{{ __('kds.pin_label') }}</label>
                    <input
                        id="kds-pin"
                        name="pin"
                        type="password"
                        inputmode="numeric"
                        pattern="\d{4}"
                        maxlength="4"
                        autocomplete="one-time-code"
                        required
                        autofocus
                        value="{{ old('pin') }}"
                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-4 text-center text-2xl font-mono tracking-[0.5em] text-slate-100 placeholder:text-slate-600 focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500/40 dark:bg-slate-950 dark:text-white"
                        placeholder="••••"
                    />
                    @error('pin')
                        <p class="mt-2 text-center text-sm text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-rose-600 px-4 py-3 text-base font-semibold text-white hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400/50"
                >
                    {{ __('kds.login_submit') }}
                </button>
            </form>
        </div>
    </div>
@endsection
