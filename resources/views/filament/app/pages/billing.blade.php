<x-filament-panels::page>
    {{-- For the developer: what is still missing in .env. Customers in production never see key names. --}}
    @if ($this->getMissingKeys() && ! app()->isProduction())
        <x-filament::section icon="heroicon-o-key" heading="Add your Stripe keys">
            <x-slot name="description">
                Billing is ready and waiting for a Stripe account. Until then every workspace stays on its current plan.
            </x-slot>

            <ol style="display:flex;flex-direction:column;gap:.5rem;font-size:.875rem;list-style:decimal;padding-inline-start:1.25rem">
                <li>
                    In the Stripe dashboard (test mode), create a product for each paid plan, with a monthly recurring price and, if you sell it yearly, a yearly one.
                </li>
                <li>
                    Fill in these keys in <code>.env</code>:
                    @foreach ($this->getMissingKeys() as $key)
                        <code>{{ $key }}</code>@if (! $loop->last), @endif
                    @endforeach
                </li>
                <li>
                    Forward Stripe's webhooks to this app:
                    <code>stripe listen --forward-to {{ route('cashier.webhook') }}</code>
                </li>
            </ol>
        </x-filament::section>
    @elseif (! $this->isConfigured())
        <x-filament::section icon="heroicon-o-clock" heading="Paid plans are on their way">
            <x-slot name="description">
                You can keep working on your current plan in the meantime.
            </x-slot>
        </x-filament::section>
    @endif

    @if ($this->isConfigured() && $this->trialEndsAt())
        <x-filament::section icon="heroicon-o-gift" icon-color="primary" heading="Your free trial ends on {{ $this->trialEndsAt()->toFormattedDateString() }}">
            <x-slot name="description">
                Your card is charged then, and the plan carries on. Cancel any time before under "Manage billing".
            </x-slot>
        </x-filament::section>
    @endif

    @if ($this->isConfigured() && $this->needsFirstPayment())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning" heading="Your payment is not finished yet">
            <x-slot name="description">
                Complete it to start your plan. Until then the workspace stays on its current plan.
            </x-slot>

            {{ $this->completePaymentAction }}
        </x-filament::section>
    @elseif ($this->isConfigured() && $this->needsPayment())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning" heading="Your last payment did not go through">
            <x-slot name="description">
                Update your payment method to keep your plan. You can change plans again once the payment is settled.
            </x-slot>

            {{ $this->manageAction }}
        </x-filament::section>
    @endif

    @if ($this->getIntervals())
        <div style="display:flex;justify-content:center">
            <x-filament::tabs label="Billing interval">
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
                            <x-filament::badge color="success">Current plan</x-filament::badge>
                        @endif
                    </div>

                    @if ($plan['price'])
                        <div style="font-size:1.875rem;font-weight:700;line-height:1">{{ $plan['price'] }}</div>
                    @endif

                    @if ($plan['sold'] && $this->isConfigured() && $this->trialDaysOnOffer())
                        <x-filament::badge color="info" style="align-self:flex-start">{{ $this->trialDaysOnOffer() }}-day free trial</x-filament::badge>
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
                                Cancel your subscription under "Manage billing" to move to this plan.
                            </div>
                        @endif
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
