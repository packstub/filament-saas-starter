{{-- Inline styles on Filament's colour variables: the panel's stylesheet only holds the classes Filament itself uses. --}}
@php
    $steps = $this->getSteps();
    $done = collect($steps)->where('done', true)->count();
@endphp

<x-filament-widgets::widget>
    @unless ($dismissed)
        <x-filament::section icon="heroicon-o-rocket-launch" icon-color="primary">
            <x-slot name="heading">Get started</x-slot>
            <x-slot name="description">{{ $done }} of {{ count($steps) }} done. Each step ticks itself off once it is done.</x-slot>

            <x-slot name="afterHeader">
                <x-filament::link tag="button" color="gray" size="sm" wire:click="dismiss">
                    Dismiss
                </x-filament::link>
            </x-slot>

            <div role="progressbar" aria-label="Getting started" aria-valuemin="0" aria-valuemax="{{ count($steps) }}" aria-valuenow="{{ $done }}" style="height:.375rem;border-radius:9999px;overflow:hidden;margin-bottom:1rem;background:rgb(127 127 127 / .2)">
                <div style="height:100%;width:{{ round($done / count($steps) * 100) }}%;background:var(--primary-500)"></div>
            </div>

            <ul style="display:grid;gap:.75rem;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr))">
                @foreach ($steps as $step)
                    <li data-step="{{ $step['key'] }}" data-done="{{ $step['done'] ? 'true' : 'false' }}" style="display:flex;gap:.75rem;padding:.875rem;border-radius:.75rem;box-shadow:0 0 0 1px rgb(127 127 127 / .25)">
                        <x-filament::icon
                            :icon="$step['done'] ? 'heroicon-s-check-circle' : 'heroicon-o-stop-circle'"
                            style="width:1.5rem;height:1.5rem;flex:none;color:{{ $step['done'] ? 'var(--success-500)' : 'var(--gray-400)' }}"
                        />

                        <div style="display:flex;flex-direction:column;align-items:flex-start;gap:.25rem">
                            <span style="font-weight:600;font-size:.875rem;{{ $step['done'] ? 'text-decoration:line-through;color:var(--gray-500)' : '' }}">
                                {{ $step['title'] }}
                                @if ($step['done'])
                                    <span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap">(done)</span>
                                @endif
                            </span>
                            <span style="font-size:.875rem;color:var(--gray-500)">{{ $step['description'] }}</span>

                            @if (! $step['done'] && $step['url'])
                                <x-filament::link :href="$step['url']" size="sm" style="margin-top:.25rem">
                                    {{ $step['action'] }} &rarr;
                                </x-filament::link>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endunless
</x-filament-widgets::widget>
