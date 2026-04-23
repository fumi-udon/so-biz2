<div
    @class([
        'w-full shrink-0 border-t border-yellow-200 bg-yellow-50/90 dark:border-yellow-800 dark:bg-yellow-900/30',
    ])
    x-data="{ peerTakeawayTid: null }"
    x-on:pos-floor-peer-sync.window="peerTakeawayTid = ($event.detail && $event.detail.takeawayFloorTid != null) ? Number($event.detail.takeawayFloorTid) : null"
    x-on:pos-tile-interaction-ended.window="peerTakeawayTid = null"
    data-takeaway-bar="true"
    role="region"
    aria-label="{{ __('pos.takeaway_region_label') }}"
>
    <div class="px-4 py-4 sm:px-6 sm:py-5">
        <p class="mb-0.5 text-[12px] font-extrabold uppercase tracking-wider text-yellow-900 dark:text-yellow-100 sm:text-[10px]">
            {{ __('pos.takeaway_heading') }}
        </p>
        @if (count($takeawayTiles) === 0)
            <p class="text-[10px] text-yellow-950/80 dark:text-yellow-100/90">
                {{ __('pos.takeaway_no_tables') }}
            </p>
        @else
            {{-- 6 卓固定・1 行。選択: amber 反転 + scale / クリックフラッシュ: app.css --}}
            <div class="flex w-full min-w-0 flex-nowrap gap-0.5 overflow-visible py-0.5 sm:gap-1">
                @foreach ($takeawayTiles as $tile)
                    @php
                        $tid = (int) $tile['restaurantTableId'];
                        $isFloorSel = $this->floorSelectedTakeawayTableId !== null && $this->floorSelectedTakeawayTableId === $tid;
                        $surface = $this->tileSurfaceClasses($tile);
                    @endphp
                    <button
                        type="button"
                        wire:click="openModalForTable({{ $tid }})"
                        wire:key="takeaway-tile-{{ $tid }}"
                        x-data="{ flash: false, flashTimer: null }"
                        x-on:click="
                            flash = true;
                            if (flashTimer) clearTimeout(flashTimer);
                            flashTimer = setTimeout(() => { flash = false; flashTimer = null }, 450);
                        "
                        x-bind:class="{ 'pos-tile-select-flash': flash }"
                        data-ui-status="{{ $tile['uiStatus'] ?? 'free' }}"
                        title="{{ $this->tileLabel($tile) }}"
                        @class([
                            'relative z-0 inline-flex min-h-10 min-w-0 flex-1 basis-0 items-center justify-center rounded border px-0.5 py-2 text-[14px] leading-none shadow-sm transition duration-150 ease-out focus:outline-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 sm:min-h-[4rem] sm:px-1 sm:py-3 sm:text-[10px] '.$surface,
                            '!z-10 !scale-110 ring-4 ring-inset ring-amber-600 dark:ring-amber-400' => $isFloorSel,
                            'font-semibold' => ! $isFloorSel,
                            'font-black' => $isFloorSel,
                        ])
                        x-bind:class="(peerTakeawayTid === {{ $tid }} && ! @js($isFloorSel)) ? '!z-10 !scale-110 ring-4 ring-inset ring-amber-600 dark:ring-amber-400 font-black' : ''"
                    >
                        <span @class(['truncate', 'font-black' => $isFloorSel])>{{ $this->tileLabel($tile) }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($modalOpen && $selectedTableId !== null)
        @teleport('body')
            <div
                data-pos-takeaway-guest-modal="true"
                wire:key="takeaway-guest-modal-shell-{{ $selectedTableId }}"
                x-data
                x-init="
                    $nextTick(() => {
                        window.dispatchEvent(
                            new CustomEvent('open-modal', {
                                detail: { id: 'pos-takeaway-guest-modal' },
                                bubbles: true,
                            }),
                        )
                    })
                "
                x-on:close-modal.window="
                    if ($event.detail && $event.detail.id === 'pos-takeaway-guest-modal') {
                        $wire.closeModal()
                    }
                "
            >
                <x-filament::modal
                    id="pos-takeaway-guest-modal"
                    :heading="__('pos.takeaway_modal_title', ['id' => $selectedTableId])"
                    :description="__('pos.takeaway_modal_subtitle')"
                    width="md"
                    display-classes="block"
                    :close-button="true"
                    :close-by-clicking-away="true"
                    :close-by-escaping="true"
                >
                    <div class="space-y-4">
                        <div
                            class="fi-fo-field-wrp"
                            data-field-wrapper
                        >
                            <div
                                class="grid gap-y-2"
                            >
                                <label
                                    class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white"
                                    for="takeaway-customer-name-{{ $selectedTableId }}"
                                >
                                    {{ __('pos.takeaway_customer_name') }}
                                </label>
                                <x-filament::input.wrapper
                                    :valid="! $errors->has('customerName')"
                                >
                                    <x-filament::input
                                        id="takeaway-customer-name-{{ $selectedTableId }}"
                                        type="text"
                                        wire:model="customerName"
                                        autocomplete="name"
                                    />
                                </x-filament::input.wrapper>
                                @error('customerName')
                                    <p class="fi-fo-field-wrp-error-message text-sm text-danger-600 dark:text-danger-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>

                        <div
                            class="fi-fo-field-wrp"
                            data-field-wrapper
                        >
                            <div
                                class="grid gap-y-2"
                            >
                                <label
                                    class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white"
                                    for="takeaway-customer-phone-{{ $selectedTableId }}"
                                >
                                    {{ __('pos.takeaway_customer_phone') }}
                                </label>
                                <x-filament::input.wrapper
                                    :valid="! $errors->has('customerPhone')"
                                >
                                    <x-filament::input
                                        id="takeaway-customer-phone-{{ $selectedTableId }}"
                                        type="tel"
                                        wire:model="customerPhone"
                                        inputmode="tel"
                                        autocomplete="tel"
                                    />
                                </x-filament::input.wrapper>
                                @error('customerPhone')
                                    <p class="fi-fo-field-wrp-error-message text-sm text-danger-600 dark:text-danger-400">
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <x-slot name="footer">
                        <div
                            class="fi-modal-footer-actions flex flex-wrap items-center gap-3"
                        >
                            <x-filament::button
                                type="button"
                                color="gray"
                                outlined
                                wire:click="closeModal"
                            >
                                {{ __('pos.takeaway_cancel') }}
                            </x-filament::button>
                            <x-filament::button
                                type="button"
                                color="warning"
                                wire:click="confirmTakeawayGuest"
                                wire:loading.attr="disabled"
                                wire:target="confirmTakeawayGuest"
                            >
                                {{ __('pos.takeaway_confirm') }}
                            </x-filament::button>
                        </div>
                    </x-slot>
                </x-filament::modal>
            </div>
        @endteleport
    @endif
</div>
