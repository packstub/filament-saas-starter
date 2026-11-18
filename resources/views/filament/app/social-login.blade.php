{{-- Under the sign-in and sign-up forms: one button per provider set up in config/services.php. --}}
@if ($providers)
    <div style="display:flex;flex-direction:column;gap:.75rem">
        <div style="display:flex;align-items:center;gap:.75rem;font-size:.875rem" class="text-gray-500 dark:text-gray-400">
            <span style="flex:1;height:1px" class="bg-gray-200 dark:bg-white/10"></span>
            or
            <span style="flex:1;height:1px" class="bg-gray-200 dark:bg-white/10"></span>
        </div>

        @foreach ($providers as $provider => $label)
            <x-filament::button tag="a" :href="route('social.redirect', $provider)" color="gray" outlined size="lg">
                <span style="display:inline-flex;align-items:center;gap:.5rem">
                    @include('filament.app.social-icon', ['provider' => $provider])
                    Continue with {{ $label }}
                </span>
            </x-filament::button>
        @endforeach
    </div>
@endif
