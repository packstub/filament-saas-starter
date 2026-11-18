<?php

use App\Events\AccountDeleting;
use App\Filament\App\Pages\Auth\EditProfile;
use App\Filament\App\Pages\Tenancy\EditWorkspace;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DataExport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

/**
 * Upload a logo through Workspace settings, as the given member (the owner by default).
 */
function uploadLogo(Workspace $workspace, ?UploadedFile $file = null, ?User $user = null): void
{
    inWorkspace($workspace, $user);

    livewire(EditWorkspace::class)
        ->fillForm(['logo_path' => $file ?? UploadedFile::fake()->image('logo.png', 200, 200)])
        ->call('save')
        ->assertHasNoFormErrors();
}

it('stores a workspace logo in the workspace\'s own folder and shows it in the tenant menu', function (string $role) {
    $workspace = workspace();

    uploadLogo($workspace, user: memberOf($workspace, $role));

    $path = $workspace->fresh()->logo_path;

    expect($path)->toStartWith("workspaces/{$workspace->id}/logo/")
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and($workspace->fresh()->getFilamentAvatarUrl())->toBe(Storage::disk('public')->url($path));

    get("/app/{$workspace->slug}")->assertOk()->assertSee(Storage::disk('public')->url($path), false);
})->with(['owner', 'admin']);

it('refuses files that are too large or not an image', function (UploadedFile $file) {
    $workspace = workspace();
    inWorkspace($workspace);

    livewire(EditWorkspace::class)
        ->fillForm(['logo_path' => $file])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);

    expect($workspace->fresh()->logo_path)->toBeNull();
})->with([
    'too large' => fn () => UploadedFile::fake()->image('logo.png')->size(config('uploads.max_size') + 1),
    'an SVG' => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'a PDF' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
]);

it('removes the logo it replaces', function () {
    $workspace = workspace();

    uploadLogo($workspace);
    $first = $workspace->fresh()->logo_path;

    livewire(EditWorkspace::class)
        ->fillForm(['logo_path' => []])
        ->fillForm(['logo_path' => UploadedFile::fake()->image('new.png')])
        ->call('save')
        ->assertHasNoFormErrors();
    $second = $workspace->fresh()->logo_path;

    expect($second)->not->toBe($first)
        ->and(Storage::disk('public')->exists($first))->toBeFalse()
        ->and(Storage::disk('public')->exists($second))->toBeTrue();
});

it('never points a workspace at another workspace\'s file', function () {
    $acme = workspace();
    $globex = workspace();
    uploadLogo($globex);
    $theirs = $globex->fresh()->logo_path;

    expect(fn () => $acme->update(['logo_path' => $theirs]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $acme->update(['logo_path' => "workspaces/{$acme->id}/logo/../../{$globex->id}/logo/x.png"]))->toThrow(InvalidArgumentException::class)
        ->and($acme->fresh()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->exists($theirs))->toBeTrue();
});

it('never deletes a file outside the workspace\'s folder when its logo changes', function () {
    $acme = workspace();
    $globex = workspace();
    uploadLogo($globex);
    $theirs = $globex->fresh()->logo_path;

    // A path written around the model's check, straight into the table.
    Workspace::query()->whereKey($acme->id)->update(['logo_path' => $theirs]);
    $acme->refresh()->update(['logo_path' => null]);

    expect(Storage::disk('public')->exists($theirs))->toBeTrue();
});

it('keeps the logo from members', function () {
    $workspace = workspace();
    inWorkspace($workspace, memberOf($workspace, 'member'));

    livewire(EditWorkspace::class)->assertNotFound();
});

it('removes a deleted workspace\'s files, and nothing of another\'s', function () {
    $acme = workspace();
    $globex = workspace();
    uploadLogo($acme);
    uploadLogo($globex);
    Storage::disk('public')->put("workspaces/{$acme->id}/attachments/brief.pdf", 'pdf');

    $acme->fresh()->delete();

    expect(Storage::disk('public')->directoryExists("workspaces/{$acme->id}"))->toBeFalse()
        ->and(Storage::disk('public')->exists($globex->fresh()->logo_path))->toBeTrue();
});

it('keeps the files of a workspace whose deletion rolled back', function () {
    $workspace = workspace();
    uploadLogo($workspace);
    $path = $workspace->fresh()->logo_path;

    Workspace::deleted(fn () => throw new RuntimeException('The database went away.'));

    expect(fn () => $workspace->fresh()->delete())->toThrow(RuntimeException::class)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('stores a profile picture in the person\'s own folder and shows it in the user menu', function () {
    $workspace = workspace();
    $user = inWorkspace($workspace);

    livewire(EditProfile::class)
        ->fillForm(['avatar_path' => UploadedFile::fake()->image('me.jpg', 200, 200)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = $user->fresh()->avatar_path;

    expect($path)->toStartWith("users/{$user->id}/avatar/")
        ->and(Storage::disk('public')->exists($path))->toBeTrue();

    actingAs($user->fresh());
    get("/app/{$workspace->slug}")->assertOk()->assertSee(Storage::disk('public')->url($path), false);
});

it('never points an account at someone else\'s file', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect(fn () => $user->update(['avatar_path' => "users/{$other->id}/avatar/me.png"]))->toThrow(InvalidArgumentException::class);
});

it('removes a deleted account\'s files, and its own workspace\'s, and nobody else\'s', function () {
    $user = User::factory()->create();
    $own = workspace(owner: $user);
    $shared = workspace('pro');
    memberOf($shared, 'member');
    Teams::addMember($shared, $user, 'member');

    Storage::disk('public')->put("users/{$user->id}/avatar/me.png", 'png');
    $user->update(['avatar_path' => "users/{$user->id}/avatar/me.png"]);
    Storage::disk('public')->put("workspaces/{$own->id}/logo/own.png", 'png');
    Storage::disk('public')->put("workspaces/{$shared->id}/logo/shared.png", 'png');

    $user->fresh()->deleteAccount();

    expect(Storage::disk('public')->directoryExists("users/{$user->id}"))->toBeFalse()
        ->and(Storage::disk('public')->directoryExists("workspaces/{$own->id}"))->toBeFalse()
        ->and(Storage::disk('public')->exists("workspaces/{$shared->id}/logo/shared.png"))->toBeTrue();
});

it('lists the logo and the profile picture in the data exports', function () {
    $workspace = workspace();
    uploadLogo($workspace);
    $owner = Teams::ownersOf($workspace)->first();

    expect(DataExport::forWorkspace($workspace->fresh())['workspace']['logo'])->toBe(Storage::disk('public')->url($workspace->fresh()->logo_path))
        ->and(DataExport::forUser($owner)['account']['avatar'])->toBeNull();
});

it('stores uploads as public files, since their plain URL is shown', function () {
    $workspace = workspace();
    uploadLogo($workspace);

    expect(config('uploads.visibility'))->toBe('public')
        ->and(Storage::disk('public')->getVisibility($workspace->fresh()->logo_path))->toBe('public');
});

it('keeps the replaced logo when the save rolls back', function () {
    $workspace = workspace();
    uploadLogo($workspace);
    $old = $workspace->fresh()->logo_path;
    Storage::disk('public')->put("workspaces/{$workspace->id}/logo/new.png", 'png');

    expect(fn () => DB::transaction(function () use ($workspace): void {
        $workspace->fresh()->update(['logo_path' => "workspaces/{$workspace->id}/logo/new.png"]);

        throw new RuntimeException('The database went away.');
    }))->toThrow(RuntimeException::class);

    expect($workspace->fresh()->logo_path)->toBe($old)
        ->and(Storage::disk('public')->exists($old))->toBeTrue();
});

it('lets a new workspace or account be created with a picture already set', function () {
    $workspace = Workspace::query()->create(['name' => 'Seeded', 'slug' => 'seeded', 'logo_path' => 'seed/logo.png']);
    $user = User::factory()->create(['avatar_path' => 'seed/avatar.png']);

    expect($workspace->logo_path)->toBe('seed/logo.png')
        ->and($user->avatar_path)->toBe('seed/avatar.png');
});

it('keeps the files of an account whose deletion rolled back', function () {
    $user = User::factory()->create();
    Storage::disk('public')->put("users/{$user->id}/avatar/me.png", 'png');

    Event::listen(AccountDeleting::class, fn () => throw new RuntimeException('The database went away.'));

    expect(fn () => $user->deleteAccount())->toThrow(RuntimeException::class)
        ->and(Storage::disk('public')->exists("users/{$user->id}/avatar/me.png"))->toBeTrue();
});
