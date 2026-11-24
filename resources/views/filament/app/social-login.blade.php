{{-- Under the sign-in and sign-up forms: one button per provider set up in config/services.php.
     Inline styles: the panel's stylesheet only holds the classes Filament itself uses. --}}
@if ($providers)
    <div style="display:flex;flex-direction:column;gap:.75rem">
        <div style="display:flex;align-items:center;gap:.75rem;font-size:.875rem;color:var(--gray-500)">
            <span style="flex:1;height:1px;background:rgb(127 127 127 / .25)"></span>
            {{ __('ui_social.or') }}
            <span style="flex:1;height:1px;background:rgb(127 127 127 / .25)"></span>
        </div>

        @foreach ($providers as $provider => $label)
            <x-filament::button tag="a" :href="route('social.redirect', $provider)" color="gray" outlined size="lg">
                <span style="display:inline-flex;align-items:center;gap:.5rem">
                    @include('filament.app.social-icon', ['provider' => $provider])
                    {{ __('ui_social.continue_with', ['provider' => $label]) }}
                </span>
            </x-filament::button>
        @endforeach
    </div>
@endif
