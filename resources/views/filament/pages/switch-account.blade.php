<x-filament-panels::page>
    <form wire:submit="switchAccount" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            Switch workspace
        </x-filament::button>
    </form>
</x-filament-panels::page>
