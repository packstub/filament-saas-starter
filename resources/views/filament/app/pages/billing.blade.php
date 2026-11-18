<x-filament-panels::page>
    {{-- For the developer: what is still missing in .env. Customers in production never see key names. --}}
    @if ($this->getMissingKeys() && ! app()->isProduction())
        <x-filament::section icon="heroicon-o-key" :heading="__('billing.setup.heading')">
            <x-slot name="description">
                {{ __('billing.setup.description') }}
            </x-slot>

            <ol style="display:flex;flex-direction:column;gap:.5rem;font-size:.875rem;list-style:decimal;padding-inline-start:1.25rem">
                <li>
                    {{ __('billing.setup.step_product') }}
                </li>
                <li>
                    {!! __('billing.setup.step_keys') !!}
                    @foreach ($this->getMissingKeys() as $key)
                        <code>{{ $key }}</code>@if (! $loop->last), @endif
                    @endforeach
                </li>
                <li>
                    {{ __('billing.setup.step_webhook') }}
                    <code>stripe listen --forward-to {{ route('cashier.webhook') }}</code>
                </li>
            </ol>
        </x-filament::section>
    @elseif (! $this->isConfigured())
        <x-filament::section icon="heroicon-o-clock" :heading="__('billing.coming_soon.heading')">
            <x-slot name="description">
                {{ __('billing.coming_soon.description') }}
            </x-slot>
        </x-filament::section>
    @endif

    @if ($this->isConfigured() && $this->trialEndsAt())
        <x-filament::section icon="heroicon-o-gift" icon-color="primary" :heading="__('billing.trial.heading', ['date' => $this->trialEndsAt()->isoFormat('ll')])">
            <x-slot name="description">
                {{ __('billing.trial.description') }}
            </x-slot>
        </x-filament::section>
    @endif

    @if ($this->isConfigured() && $this->needsFirstPayment())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning" :heading="__('billing.first_payment.heading')">
            <x-slot name="description">
                {{ __('billing.first_payment.description') }}
            </x-slot>

            {{ $this->completePaymentAction }}
        </x-filament::section>
    @elseif ($this->isConfigured() && $this->isPaused())
        <x-filament::section icon="heroicon-o-pause-circle" icon-color="warning" :heading="__('billing.paused.heading')">
            <x-slot name="description">
                {{ __('billing.paused.description') }}
            </x-slot>

            <div style="display:flex;gap:.75rem;flex-wrap:wrap">
                {{ $this->manageAction }}
                {{ $this->resumeAction }}
            </div>
        </x-filament::section>
    @elseif ($this->isConfigured() && $this->needsPayment())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning" :heading="__('billing.payment_failed.heading')">
            <x-slot name="description">
                {{ __('billing.payment_failed.description') }}
            </x-slot>

            {{ $this->manageAction }}
        </x-filament::section>
    @endif

    @if ($this->getIntervals())
        <div style="display:flex;justify-content:center">
            <x-filament::tabs :label="__('billing.interval_label')">
                @foreach ($this->getIntervals() as $key => $label)
                    <x-filament::tabs.item :active="$this->selectedInterval() === $key" wire:click="$set('interval', '{{ $key }}')">
                        {{ $label }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        </div>
    @endif

    <div style="display:grid;gap:1.5rem;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr))">
        @foreach ($this->getPlans() as $plan)
            <x-filament::section data-plan="{{ $plan['key'] }}">
                <div style="display:flex;flex-direction:column;gap:1rem;height:100%">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem">
                        <div style="font-size:1rem;font-weight:600">{{ $plan['label'] }}</div>
                        @if ($plan['current'])
                            <x-filament::badge color="success">{{ __('billing.plan.current') }}</x-filament::badge>
                        @endif
                    </div>

                    @if ($plan['price'])
                        <div style="font-size:1.875rem;font-weight:700;line-height:1">{{ $plan['price'] }}</div>
                    @endif

                    @if ($plan['sold'] && $this->isConfigured() && $this->trialDaysOnOffer())
                        <x-filament::badge color="info" style="align-self:flex-start">{{ trans_choice('billing.trial.badge', $this->trialDaysOnOffer(), ['days' => $this->trialDaysOnOffer()]) }}</x-filament::badge>
                    @endif

                    @if ($plan['per_seat'])
                        <div style="font-size:.75rem;opacity:.7">
                            {{ trans_choice('billing.plan.per_seat', $this->billableSeats()) }}
                        </div>
                    @endif

                    @if ($plan['description'])
                        <div style="font-size:.875rem;opacity:.7">{{ $plan['description'] }}</div>
                    @endif

                    <ul style="display:flex;flex-direction:column;gap:.375rem;font-size:.875rem">
                        @foreach ($plan['lines'] as $line)
                            <li style="display:flex;align-items:center;gap:.5rem">
                                <x-filament::icon icon="heroicon-m-check" style="width:1rem;height:1rem;color:var(--color-primary-600, #4f46e5)" />
                                {{ $line }}
                            </li>
                        @endforeach
                    </ul>

                    @if ($this->isConfigured() && ! $plan['current'] && ! $this->needsPayment())
                        @if ($plan['sold'])
                            <div style="margin-top:auto">
                                {{ ($this->hasSubscription() ? $this->changePlanAction : $this->subscribeAction)(['plan' => $plan['key'], 'interval' => $this->selectedInterval()]) }}
                            </div>
                        @elseif ($this->hasSubscription())
                            <div style="margin-top:auto;font-size:.75rem;opacity:.7">
                                {{ __('billing.plan.cancel_to_switch') }}
                            </div>
                        @endif
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
