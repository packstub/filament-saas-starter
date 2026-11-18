<x-filament-panels::page>
    @if ($plainTextToken)
        <x-filament::section icon="heroicon-o-key" icon-color="success" heading="Copy your new token now">
            <x-slot name="description">
                It is shown only this once. Send it as a bearer token: <code>Authorization: Bearer &lt;token&gt;</code>.
            </x-slot>

            <div x-data="{ copied: false }" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
                <x-filament::input.wrapper style="flex:1;min-width:16rem">
                    <x-filament::input type="text" readonly :value="$plainTextToken" x-ref="token" aria-label="Your new API token" style="font-family:ui-monospace,monospace" />
                </x-filament::input.wrapper>

                <x-filament::button color="gray" icon="heroicon-o-clipboard-document" x-on:click="navigator.clipboard.writeText($refs.token.value); copied = true">
                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                </x-filament::button>

                <x-filament::button color="gray" wire:click="dismissToken">
                    Done
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
