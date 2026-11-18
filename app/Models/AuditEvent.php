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
