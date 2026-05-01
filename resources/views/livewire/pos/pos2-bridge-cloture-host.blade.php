<div class="min-h-[100dvh] p-2" wire:init="openClotureModal">
    <livewire:pos.cloture-modal
        :shop-id="$shopId"
        wire:key="pos2-bridge-cloture-modal-{{ $shopId }}"
    />
</div>
