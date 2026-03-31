<div class="timecard-livewire">
    <header class="text-center mb-4">
        <p class="text-info small text-uppercase mb-1 tracking-wide">タイムカード</p>
        <h1 class="h3 fw-semibold mb-3">打刻</h1>
        <p class="text-secondary small mb-1">
            営業日
            <time class="text-light font-monospace" datetime="{{ $targetBusinessDate->toDateString() }}">
                {{ $targetBusinessDate->format('Y/m/d') }}
            </time>
        </p>
        <p class="text-secondary mb-0" style="font-size: 0.7rem;">6時前の打刻は前営業日として記録されます（夜勤など）。</p>
    </header>

    @if ($bannerSuccess)
        <div class="alert alert-success border-0 shadow-sm mb-3 py-3 text-center fw-semibold" role="status">
            {{ $bannerSuccess }}
        </div>
    @endif

    @if ($bannerError)
        <div class="alert alert-danger border-0 mb-3 py-3" role="alert">
            {{ $bannerError }}
        </div>
    @endif

    @if ($staffOptions === [])
        <div class="border border-secondary border-2 border-dashed rounded-3 p-5 text-center text-secondary">
            アクティブなスタッフが登録されていません。
        </div>
    @elseif ($step === 1)
        <div class="rounded-3 border border-secondary bg-black bg-opacity-25 p-4">
            <p class="small text-secondary mb-3">Step 1 — 本人確認</p>
            <div class="mb-3">
                <label for="tc_staff" class="form-label small text-secondary">スタッフ</label>
                <select
                    id="tc_staff"
                    wire:model.live="selectedStaffId"
                    class="form-select form-select-lg"
                >
                    <option value="">選択してください</option>
                    @foreach ($staffOptions as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                    @endforeach
                </select>
                @error('selectedStaffId')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-4">
                <label for="tc_pin" class="form-label small text-secondary">PIN（4桁）</label>
                <input
                    id="tc_pin"
                    type="password"
                    wire:model.live.debounce.500ms="pinCode"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="4"
                    class="form-control form-control-lg text-center font-monospace"
                    placeholder="••••"
                />
                @error('pinCode')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>
            <button
                type="button"
                wire:click="authenticate"
                wire:loading.attr="disabled"
                class="btn btn-primary btn-lg w-100 py-4 fs-4 fw-bold"
            >
                <span wire:loading.remove wire:target="authenticate">次へ</span>
                <span wire:loading wire:target="authenticate">確認中…</span>
            </button>
        </div>
    @else
        <div class="mb-4 rounded-3 border border-info border-opacity-50 bg-info bg-opacity-10 px-3 py-3 text-center">
            <p class="mb-0 fs-4 fw-semibold text-light">
                お疲れ様です、{{ $authenticatedStaffName }} さん
            </p>
            <button type="button" wire:click="backToAuth" class="btn btn-link btn-sm text-secondary mt-2 px-0">
                別のスタッフに切り替える
            </button>
        </div>

        @php
            $s = $shiftState;
            $showLunchBlock = $s['lunch_scheduled'] || $s['lunch_in'];
            $showDinnerBlock = $s['dinner_scheduled'] || $s['dinner_in'];
            $canExtraLunch = ! $s['lunch_scheduled'] && ! $s['lunch_in'];
            $canExtraDinner = ! $s['dinner_scheduled'] && ! $s['dinner_in'];
            $extraAvailable = $canExtraLunch || $canExtraDinner;
        @endphp

        <div class="d-flex flex-column gap-4 align-items-stretch justify-content-center mx-auto w-100" style="max-width: 28rem;">
            @if ($showLunchBlock)
                <section class="timecard-meal-block w-100" aria-label="ランチ打刻">
                    <p class="text-center text-secondary small text-uppercase mb-2">ランチ</p>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <button
                                type="button"
                                wire:click="punch('lunch_in')"
                                wire:loading.attr="disabled"
                                @disabled($this->isPunchDisabled('lunch_in'))
                                class="btn btn-lg w-100 py-4 fs-4 fw-bold border-2 {{ $this->isPunchDisabled('lunch_in') ? 'btn-secondary opacity-50 cursor-not-allowed bg-secondary border-secondary' : 'btn-success' }}"
                            >
                                ランチ出勤
                            </button>
                        </div>
                        <div class="col-12 col-sm-6">
                            <button
                                type="button"
                                wire:click="punch('lunch_out')"
                                wire:loading.attr="disabled"
                                @disabled($this->isPunchDisabled('lunch_out'))
                                class="btn btn-lg w-100 py-4 fs-4 fw-bold border-2 {{ $this->isPunchDisabled('lunch_out') ? 'btn-secondary opacity-50 cursor-not-allowed bg-secondary border-secondary' : 'btn-outline-success' }}"
                            >
                                ランチ退勤
                            </button>
                        </div>
                    </div>
                </section>
            @endif

            @if ($showLunchBlock && $showDinnerBlock)
                <hr class="border-secondary my-0 opacity-50">
            @endif

            @if ($showDinnerBlock)
                <section class="timecard-meal-block w-100" aria-label="ディナー打刻">
                    <p class="text-center text-secondary small text-uppercase mb-2">ディナー</p>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <button
                                type="button"
                                wire:click="punch('dinner_in')"
                                wire:loading.attr="disabled"
                                @disabled($this->isPunchDisabled('dinner_in'))
                                class="btn btn-lg w-100 py-4 fs-4 fw-bold border-2 {{ $this->isPunchDisabled('dinner_in') ? 'btn-secondary opacity-50 cursor-not-allowed bg-secondary border-secondary' : 'btn-warning text-dark' }}"
                            >
                                ディナー出勤
                            </button>
                        </div>
                        <div class="col-12 col-sm-6">
                            <button
                                type="button"
                                wire:click="punch('dinner_out')"
                                wire:loading.attr="disabled"
                                @disabled($this->isPunchDisabled('dinner_out'))
                                class="btn btn-lg w-100 py-4 fs-4 fw-bold border-2 {{ $this->isPunchDisabled('dinner_out') ? 'btn-secondary opacity-50 cursor-not-allowed bg-secondary border-secondary' : 'btn-outline-warning text-warning' }}"
                            >
                                ディナー退勤
                            </button>
                        </div>
                    </div>
                </section>
            @endif
        </div>

        <div class="rounded-3 border border-warning border-2 bg-warning bg-opacity-10 p-4 mt-4 mb-3">
            <p class="small fw-bold text-warning-emphasis mb-2">臨時出勤（ヘルプ）</p>
            @if ($extraAvailable)
                @if ($this->allMainPunchesDisabled())
                    <p class="small text-warning-emphasis mb-3 fw-semibold">
                        ⚠️ 本日のシフト予定がありません。ヘルプ等の場合は以下から申請してください。
                    </p>
                @else
                    <p class="small text-warning-emphasis mb-3 fw-semibold">
                        ⚠️ シフト未登録の区間は通常ボタンでは打刻できません。ヘルプの場合はこちらから申請してください。
                    </p>
                @endif
                <div class="mb-3">
                    <label class="form-label small text-secondary">臨時出勤するシフト</label>
                    <select wire:model.live="extraMeal" class="form-select form-select-lg">
                        @if ($canExtraLunch)
                            <option value="lunch">ランチ（L）</option>
                        @endif
                        @if ($canExtraDinner)
                            <option value="dinner">ディナー（D）</option>
                        @endif
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small text-secondary">理由（任意）</label>
                    <textarea wire:model.live.debounce.500ms="extraReason" class="form-control" rows="2" maxlength="500" placeholder="例：欠勤カバー"></textarea>
                </div>
                <button
                    type="button"
                    wire:click="submitExtraShift"
                    wire:loading.attr="disabled"
                    class="btn btn-warning btn-lg w-100 py-4 fs-4 fw-bold text-dark"
                >
                    <span wire:loading.remove wire:target="submitExtraShift">臨時出勤として打刻する</span>
                    <span wire:loading wire:target="submitExtraShift">処理中…</span>
                </button>
                @error('extraMeal')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            @else
                <p class="small text-secondary mb-0">
                    現在、臨時出勤の申請は不要です。（予定外のシフトが発生した場合は、管理者にご相談ください。）
                </p>
            @endif
        </div>
    @endif

    <x-filament::modal
        id="tip-result-modal"
        :close-by-clicking-away="true"
        :close-by-escaping="true"
        width="md"
        x-on:modal-closed.window="if ($event.detail.id === 'tip-result-modal') { $wire.declineTipAndRedirect() }"
    >
        @if ($tipModalState === 'WIN')
            <div class="rounded-xl bg-linear-to-r from-amber-400 to-yellow-500 p-4 text-gray-950">
                <p class="mb-2 text-lg font-black">🎉 YOU WIN!</p>
                <p class="mb-4 text-sm font-bold">定刻クリア。チップ申請権を獲得しました！</p>
                <button
                    type="button"
                    wire:click="applyForTip"
                    class="w-full rounded-lg bg-black/85 px-3 py-2 text-sm font-extrabold text-yellow-200"
                >
                    🪙 チップ権利を受け取る
                </button>
            </div>
        @elseif ($tipModalState === 'LOSE')
            <div class="rounded-xl bg-linear-to-r from-red-800 to-gray-900 p-4 text-gray-100">
                <p class="mb-2 text-lg font-black">💀 YOU LOSE...</p>
                <p class="mb-4 text-sm font-bold">遅刻判定。本シフトのチップ権利を喪失しました。</p>
                <button
                    type="button"
                    wire:click="declineTipAndRedirect"
                    class="w-full rounded-lg bg-gray-600 px-3 py-2 text-sm font-extrabold text-gray-100"
                >
                    閉じる
                </button>
            </div>
        @endif
    </x-filament::modal>
</div>
