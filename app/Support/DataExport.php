<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;
use Laravel\Cashier\Subscription;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Packstub\Features\Models\FeatureOverride;
use Packstub\Teams\Facades\Teams;
use Packstub\Teams\Models\TeamInvitation;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The data exports people download: everything the app holds about one
 * person (the Profile page), and everything a workspace holds (Workspace
 * settings, for owners). Both are JSON, written to the browser as they are
 * read, so a workspace with many projects never sits in memory whole.
 *
 * A table you add for a workspace belongs in forWorkspace() too: a lazy
 * query streams its rows one by one, like the projects.
 */
final class DataExport
{
    /**
     * @return array<string, mixed>
     */
    public static function forUser(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => self::date($user->email_verified_at),
                'two_factor_authentication' => filled($user->getAppAuthenticationSecret()),
                'created_at' => self::date($user->created_at),
            ],
            'workspaces' => Teams::teamsOf($user)->get()->map(fn (Workspace $workspace): array => [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'role' => $workspace->pivot->role,
                'joined_at' => self::date($workspace->pivot->created_at),
            ])->all(),
            'notifications' => $user->notifications()->get()->map(fn (DatabaseNotification $notification): array => [
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => self::date($notification->read_at),
                'created_at' => self::date($notification->created_at),
            ])->all(),
            // Every time support signed in as this person.
            'support_logins' => AccountSwitch::query()->where('to_user_id', $user->getKey())->oldest('created_at')->get()
                ->map(fn (AccountSwitch $switch): array => [
                    'reason' => $switch->getAttribute('reason'),
                    'at' => self::date($switch->getAttribute('created_at')),
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forWorkspace(Workspace $workspace): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'workspace' => [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'plan' => $workspace->plan,
                'created_at' => self::date($workspace->created_at),
            ],
            'members' => Teams::membersOf($workspace)->get()->map(fn (User $member): array => [
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'joined_at' => self::date($member->pivot->created_at),
            ])->all(),
            'invitations' => Teams::invitationsOf($workspace)->get()->map(fn (TeamInvitation $invitation): array => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => self::date($invitation->expires_at),
                'accepted_at' => self::date($invitation->accepted_at),
            ])->all(),
            'subscriptions' => $workspace->subscriptions()->oldest('id')->get()->map(fn (Subscription $subscription): array => [
                'status' => $subscription->stripe_status,
                'price' => $subscription->stripe_price,
                'quantity' => $subscription->quantity,
                'trial_ends_at' => self::date($subscription->trial_ends_at),
                'ends_at' => self::date($subscription->ends_at),
                'created_at' => self::date($subscription->created_at),
            ])->all(),
            'feature_overrides' => FeatureOverride::query()->forScope($workspace)->get()->map(fn (FeatureOverride $override): array => [
                'feature' => $override->getAttribute('feature'),
                'value' => $override->getAttribute('value'),
                'expires_at' => self::date($override->getAttribute('expires_at')),
            ])->all(),
            'activity' => AuditEvent::query()->where('workspace_id', $workspace->getKey())->lazyById(500)
                ->map(fn (AuditEvent $event): array => [
                    'event' => $event->event->value,
                    'description' => $event->description(),
                    'by' => $event->actorLabel(),
                    'at' => self::date($event->created_at),
                ]),
            'projects' => Project::query()->whereBelongsTo($workspace)->lazyById(500)
                ->map(fn (Project $project): array => collect($project->attributesToArray())->except('workspace_id')->all()),
        ];
    }

    /**
     * Send the sections as one JSON file, streaming any lazy one row by row.
     *
     * @param  array<string, mixed>  $sections
     */
    public static function download(array $sections, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($sections): void {
            $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
            $separator = '';

            echo '{';

            foreach ($sections as $key => $value) {
                echo $separator, "\n    ", json_encode($key, $flags), ': ';
                $separator = ',';

                if (! $value instanceof LazyCollection) {
                    echo str_replace("\n", "\n    ", json_encode($value, $flags));

                    continue;
                }

                $rows = '';

                echo '[';

                foreach ($value as $row) {
                    echo $rows, "\n        ", str_replace("\n", "\n        ", json_encode($row, $flags));
                    $rows = ',';
                }

                echo $rows === '' ? ']' : "\n    ]";
            }

            echo "\n}\n";
        }, $filename, ['Content-Type' => 'application/json']);
    }

    private static function date(mixed $date): ?string
    {
        return $date instanceof Carbon ? $date->toIso8601String() : $date;
    }
}
