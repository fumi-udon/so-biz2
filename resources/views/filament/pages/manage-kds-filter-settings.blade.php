<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit">
                {{ __('filament.kds_filter.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
