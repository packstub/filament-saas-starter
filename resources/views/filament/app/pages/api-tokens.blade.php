<x-filament-panels::page>
    @if ($plainTextToken)
        <x-filament::section icon="heroicon-o-key" icon-color="success" :heading="__('api_tokens.reveal.heading')">
            <x-slot name="description">
                {!! __('api_tokens.reveal.description') !!}
            </x-slot>

            <div x-data="{ copied: false }" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
                <x-filament::input.wrapper style="flex:1;min-width:16rem">
                    <x-filament::input type="text" readonly :value="$plainTextToken" x-ref="token" :aria-label="__('api_tokens.reveal.aria_label')" style="font-family:ui-monospace,monospace" />
                </x-filament::input.wrapper>

                <x-filament::button color="gray" icon="heroicon-o-clipboard-document" x-on:click="navigator.clipboard.writeText($refs.token.value); copied = true">
                    <span x-text="copied ? @js(__('api_tokens.reveal.copied')) : @js(__('api_tokens.reveal.copy'))">{{ __('api_tokens.reveal.copy') }}</span>
                </x-filament::button>

                <x-filament::button color="gray" wire:click="dismissToken">
                    {{ __('api_tokens.reveal.done') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
