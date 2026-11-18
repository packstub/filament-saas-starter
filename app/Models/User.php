<?php

namespace App\Models;

use App\Events\AccountDeleting;
use App\Support\Locales;
use App\Support\Uploads;
use App\Support\WorkspacePermissions;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;
use Packstub\Teams\Concerns\HasTeamMemberships;
use Packstub\Teams\Facades\Teams;

#[Fillable(['name', 'email', 'password', 'avatar_path', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasAvatar, HasDefaultTenant, HasLocalePreference, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasLinkedAccounts;

    // Workspace memberships and roles; also answers Filament's getTenants() and canAccessTenant().
    use HasTeamMemberships;

    // Two-factor sign-in with an authenticator app (Filament's own): the secret
    // and the recovery codes are stored encrypted and hidden from arrays and JSON.
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_admin' => false,
        'avatar_path' => null,
        'locale' => null,
    ];

    protected static function booted(): void
    {
        // A profile picture is one of the person's own files: a path saved here never points elsewhere.
        // On updates: a new record has no folder of its own yet (its id comes with the insert).
        static::updating(function (User $user): void {
            if ($user->isDirty('avatar_path') && filled($user->avatar_path)
                && ! Uploads::isInside($user->avatar_path, Uploads::userDirectory($user))) {
                throw new InvalidArgumentException('A profile picture must be stored in the person\'s own folder.');
            }
        });

        // The picture it replaced, or removed, goes from the disk once the save is committed.
        static::updated(function (User $user): void {
            if ($user->wasChanged('avatar_path')) {
                $replaced = $user->getOriginal('avatar_path');
                $directory = Uploads::userDirectory($user);

                // After the commit: a save that rolls back still points at the old file.
                DB::afterCommit(fn () => Uploads::delete($replaced, $directory));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * The language of the e-mails and notifications this person receives:
     * the one chosen on their Profile page, or the app's default.
     */
    public function preferredLocale(): ?string
    {
        // A language taken out of config/app.php since they chose it falls back to the default.
        return Locales::supports($this->locale) ? $this->locale : null;
    }

    /**
     * The profile picture, in the user menu; Filament shows initials without one.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return Uploads::url($this->avatar_path);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() !== 'admin' || $this->is_admin;
    }

    /**
     * The Google and GitHub accounts this person signs in with.
     *
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Whether this person can sign in with a password: someone who signed up
     * with Google or GitHub has none until they choose one.
     */
    public function hasPassword(): bool
    {
        return filled($this->getAuthPassword());
    }

    /**
     * How many ways this person has to sign in: a password, and each connected account.
     * The last one can never be removed, or they would be locked out.
     */
    public function signInMethodCount(): int
    {
        return ($this->hasPassword() ? 1 : 0) + $this->socialAccounts()->count();
    }

    /**
     * hasTeamPermission(), answered once per request for each workspace and
     * permission: a table asks the policy for every row and every action.
     */
    public function canInWorkspace(Workspace $workspace, string $permission): bool
    {
        return app(WorkspacePermissions::class)->allows($this, $workspace, $permission);
    }

    /**
     * The workspace a person lands in after signing in: the one they joined last.
     */
    public function getDefaultTenant(Panel $panel): ?Model
    {
        return Teams::teamsOf($this)->latest('workspace_user.created_at')->first();
    }

    /**
     * The workspaces that stop this account from being deleted: those where
     * this person is the only owner while others are still members. They
     * hand over ownership or delete the workspace first.
     *
     * @return Collection<int, Workspace>
     */
    public function workspacesBlockingDeletion(): Collection
    {
        return Teams::teamsOf($this)->get()
            ->filter(fn (Workspace $workspace): bool => $this->ownsTeam($workspace)
                && Teams::ownersOf($workspace)->count() === 1
                && Teams::membersOf($workspace)->count() > 1)
            ->values();
    }

    /**
     * Delete the account: every workspace where this person is the only
     * member goes too (its Stripe subscription cancelled first, see
     * Workspace::delete()), they leave every other one, and the audit logs
     * keep what happened but no longer their name or e-mail address
     * (AuditEvent::forget()). Leaving each workspace revokes the API tokens
     * they created there (App\Listeners\RevokeApiTokensOfFormerManagers).
     * All or nothing, in one transaction; a subscription Stripe will not
     * cancel stops it.
     */
    public function deleteAccount(): void
    {
        if ($this->workspacesBlockingDeletion()->isNotEmpty()) {
            throw new LogicException('Hand over or delete the workspaces this account is the only owner of first.');
        }

        DB::transaction(function (): void {
            foreach (Teams::teamsOf($this)->get() as $workspace) {
                Teams::membersOf($workspace)->count() === 1
                    ? $workspace->delete()
                    : Teams::removeMember($workspace, $this);
            }

            // Last, after the entries the removals above wrote.
            AuditEvent::forget($this);

            // What a module keeps about them goes in its own listener (docs/modules).
            AccountDeleting::dispatch($this);

            $this->notifications()->delete();
            $this->delete();
        });
    }

    /**
     * Support logins (Account Switcher): only admins may sign in as someone
     * else, and never as another admin.
     */
    public function canImpersonate(User $target): bool
    {
        return $this->is_admin;
    }

    public function canBeImpersonated(User $by): bool
    {
        return ! $this->is_admin;
    }
}
