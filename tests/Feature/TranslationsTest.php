<?php

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\App\Pages\Auth\EditProfile;
use App\Models\User;
use App\Notifications\AccountDeleted;
use App\Support\Locales;
use Filament\Actions\Testing\TestAction;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Livewire\livewire;

// The home page loads its built CSS; the suite runs without `npm run build`.
beforeEach(fn () => $this->withoutVite());

/**
 * Every key of every file in lang/<locale>, as `file.key.subkey`.
 *
 * @return list<string>
 */
function translationKeys(string $locale): array
{
    return collect(File::files(lang_path($locale)))
        ->flatMap(fn ($file): array => array_keys(Arr::dot([$file->getFilenameWithoutExtension() => require $file->getPathname()])))
        ->sort()
        ->values()
        ->all();
}

/**
 * Every folder in lang/ but English, compared with English. Read from the
 * disk, before the app boots, so a language added there is checked too.
 *
 * @return array<string, array{string}>
 */
function otherLocales(): array
{
    return collect(glob(dirname(__DIR__, 2).'/lang/*', GLOB_ONLYDIR))
        ->map(fn (string $directory): string => basename($directory))
        // lang/vendor holds overrides of packages' own translations, not a language.
        ->reject(fn (string $locale): bool => in_array($locale, ['en', 'vendor'], true))
        ->mapWithKeys(fn (string $locale): array => [$locale => [$locale]])
        ->all();
}

it('has a folder in lang for every language people can choose', function () {
    foreach (array_keys(Locales::all()) as $locale) {
        expect(lang_path($locale))->toBeDirectory();
    }
});

it('translates every English key', function (string $locale) {
    expect(array_values(array_diff(translationKeys('en'), translationKeys($locale))))->toBe([]);
})->with(otherLocales(...));

it('has no keys that English does not have', function (string $locale) {
    expect(array_values(array_diff(translationKeys($locale), translationKeys('en'))))->toBe([]);
})->with(otherLocales(...));

it('keeps the same placeholders in every translation', function (string $locale) {
    $placeholders = fn (string $text): array => collect(preg_match_all('/:([a-z_]+)/i', $text, $matches) ? $matches[1] : [])->unique()->sort()->values()->all();

    $different = collect(translationKeys('en'))
        ->filter(fn (string $key): bool => is_string(__($key, [], 'en')))
        ->reject(fn (string $key): bool => $placeholders(__($key, [], 'en')) === $placeholders(__($key, [], $locale)))
        ->values()
        ->all();

    expect($different)->toBe([]);
})->with(otherLocales(...));

it('speaks the language a signed-in person chose', function () {
    $workspace = workspace();
    $user = Teams::ownersOf($workspace)->first();
    $user->update(['locale' => 'es']);

    actingAs($user->fresh());

    get("/app/{$workspace->slug}")->assertOk()->assertSee(__('app.navigation.workspace', [], 'es'));
});

it('speaks the language picked on the home page before signing in', function () {
    post('/locale/es')->assertRedirect();

    expect(session('locale'))->toBe('es');

    get('/')->assertOk()->assertSee('lang="es"', false);
});

it('makes the language picked on the home page a signed-in person\'s own', function () {
    $user = User::factory()->create(['locale' => 'es']);

    actingAs($user)->post('/locale/en')->assertRedirect();

    expect($user->fresh()->locale)->toBe('en');
});

it('refuses a language it does not speak', function () {
    post('/locale/xx')->assertNotFound();
});

it('saves the language on the Profile page and opens the page again in it', function () {
    $user = inWorkspace(workspace());

    livewire(EditProfile::class)
        ->fillForm(['locale' => 'es'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(EditProfile::getUrl());

    expect($user->fresh()->locale)->toBe('es');

    livewire(EditProfile::class)
        ->fillForm(['locale' => 'fr'])
        ->call('save')
        ->assertHasFormErrors(['locale']);
});

it('sends e-mails in the language of the person they go to', function () {
    Notification::fake();

    $user = User::factory()->create(['locale' => 'es']);
    $user->deleteAccount();

    Notification::assertSentOnDemand(AccountDeleted::class, fn (AccountDeleted $notice, array $channels, AnonymousNotifiable $notifiable): bool => $notice->locale === 'es');

    expect($user->preferredLocale())->toBe('es');
});

it('switches the language only by a form with a CSRF token, never by a link', function () {
    // A GET would let any other site switch someone's language with an <img>.
    get('/locale/es')->assertMethodNotAllowed();

    expect(app('router')->getRoutes()->getByName('locale')->gatherMiddleware())->toContain('web');

    get('/')->assertOk()->assertSee('action="'.route('locale', 'es').'"', false)->assertSee('name="_token"', false);
});

it('keeps a customer\'s own language during a support login', function () {
    $workspace = workspace();
    $owner = Teams::ownersOf($workspace)->first();
    $owner->update(['locale' => 'en']);

    inAdminPanel();
    livewire(ManageUsers::class)->callAction(TestAction::make('impersonate')->table($owner));

    post('/locale/es')->assertRedirect();

    expect($owner->fresh()->locale)->toBe('en');
});

it('ignores a language it does not speak, wherever it comes from', function () {
    $user = User::factory()->create();
    $user->forceFill(['locale' => 'xx'])->saveQuietly();

    actingAs($user)->withSession(['locale' => 'yy'])->get('/')->assertOk()->assertSee('lang="en"', false);

    expect($user->fresh()->preferredLocale())->toBeNull();
});
