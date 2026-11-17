<?php

namespace App\Models;

use App\Support\WorkspacePermissions;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Packstub\AccountSwitcher\Concerns\HasLinkedAccounts;
use Packstub\Teams\Concerns\HasTeamMemberships;
use Packstub\Teams\Facades\Teams;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasDefaultTenant, HasTenants, MustVerifyEmail
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
    ];

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

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() !== 'admin' || $this->is_admin;
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
