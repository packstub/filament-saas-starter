<?php

namespace App\Models;

use App\Enums\AuditEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Throwable;

/**
 * One line of a workspace's audit log: who did what, and when. Written by
 * AuditEvent::record() (from App\Listeners\RecordWorkspaceActivity and
 * Workspace), never edited afterwards.
 *
 * The table is central, like billing and the support-login log: an entry
 * names the workspace and the people by id and keeps their names as they
 * were, so it still reads well after either is deleted, and it has no
 * foreign keys. When a workspace is deleted its entries go with it and one
 * "Workspace deleted" entry stays for the back office. Entries older than
 * `audit.retention_days` are pruned (`model:prune`, daily).
 */
#[Fillable(['workspace_id', 'workspace_name', 'event', 'actor_id', 'actor_name', 'impersonator_id', 'impersonator_name', 'properties'])]
class AuditEvent extends Model
{
    use HasUlids;
    use MassPrunable;

    public const UPDATED_AT = null;

    /** What a person who deleted their account is called in the log. */
    public const DELETED_USER = 'Deleted user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEventType::class,
            'properties' => 'array',
        ];
    }

    /**
     * Write an entry for the workspace. The actor is whoever is signed in
     * (none for a webhook or a command); during a support login the admin
     * behind it is kept too.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(Workspace $workspace, AuditEventType $event, array $properties = []): self
    {
        $actor = auth()->user();
        $impersonator = self::impersonator();

        return static::create([
            'workspace_id' => $workspace->getKey(),
            'workspace_name' => $workspace->name,
            'event' => $event,
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->getAttribute('name'),
            'impersonator_id' => $impersonator?->getAuthIdentifier(),
            'impersonator_name' => $impersonator?->getAttribute('name'),
            'properties' => $properties,
        ]);
    }

    /**
     * Take a person out of the log, after they deleted their account: their
     * name and e-mail address become "Deleted user" wherever an entry holds
     * them, as the actor, the admin behind a support login, the member an
     * entry is about, or an invited address. What happened stays.
     */
    public static function forget(User $user): void
    {
        $id = $user->getKey();

        static::query()->where('actor_id', $id)->update(['actor_name' => self::DELETED_USER]);
        static::query()->where('impersonator_id', $id)->update(['impersonator_name' => self::DELETED_USER]);

        static::query()
            ->where(fn (Builder $query) => $query
                ->where('properties->user_id', $id)
                ->orWhere('properties->from_id', $id)
                ->orWhere('properties->to_id', $id)
                ->orWhere('properties->email', $user->email))
            ->each(function (AuditEvent $event) use ($id, $user): void {
                $properties = $event->properties;

                if (($properties['user_id'] ?? null) === $id || ($properties['email'] ?? null) === $user->email) {
                    $properties['name'] = self::DELETED_USER;

                    if (isset($properties['email'])) {
                        $properties['email'] = self::DELETED_USER;
                    }
                }

                foreach (['from', 'to'] as $side) {
                    if (($properties["{$side}_id"] ?? null) === $id) {
                        $properties[$side] = self::DELETED_USER;
                    }
                }

                $event->forceFill(['properties' => $properties])->save();
            });
    }

    /**
     * The admin signed in as the actor, if this happens during a support login.
     */
    protected static function impersonator(): mixed
    {
        try {
            return AccountSwitcher::impersonator();
        } catch (Throwable) {
            // No session (a command or a queued job): nobody is impersonating.
            return null;
        }
    }

    /**
     * The workspace, while it exists.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * What happened, in a sentence: "Invited nina@example.com as Admin".
     */
    public function description(): string
    {
        $p = $this->properties ?? [];

        return match ($this->event) {
            AuditEventType::MemberAdded => "{$p['name']} joined as {$p['role']}",
            AuditEventType::MemberRemoved => "{$p['name']} is no longer a member",
            AuditEventType::RoleChanged => "{$p['name']} changed from {$p['from']} to {$p['to']}",
            AuditEventType::InvitationSent => isset($p['email']) ? "Invited {$p['email']} as {$p['role']}" : "Created an invitation link for {$p['role']}",
            AuditEventType::InvitationAccepted => "{$p['name']} accepted an invitation",
            AuditEventType::OwnershipTransferred => "Ownership moved from {$p['from']} to {$p['to']}",
            AuditEventType::ApiTokenCreated => "Created the API token {$p['name']} (".implode(', ', $p['abilities'] ?? []).')',
            AuditEventType::ApiTokenRevoked => "Revoked the API token {$p['name']}".(isset($p['from']) ? " ({$p['from']} no longer manages the workspace)" : ''),
            AuditEventType::PlanChanged => "Plan changed from {$p['from']} to {$p['to']}",
            AuditEventType::WorkspaceDeleted => "Deleted the workspace {$this->workspace_name}",
        };
    }

    /**
     * Who did it, for a table: "Olivia Bennett", "Ethan Park (support: Sam Carter)", or "System".
     */
    public function actorLabel(): string
    {
        $actor = $this->actor_name ?? 'System';

        return $this->impersonator_name ? "{$actor} (support: {$this->impersonator_name})" : $actor;
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('audit.retention_days')));
    }
}
