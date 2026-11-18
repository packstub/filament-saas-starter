# An AI assistant with credits

<!-- verify: skip until packstub/credits 1.1, packstub/agents 1.12 and packstub/filament-agents 1.19 are on Packagist -->

> [!IMPORTANT]
> This recipe needs `packstub/credits` 1.1, `packstub/agents` 1.12 and `packstub/filament-agents` 1.19, the first releases that run on PHP 8.3, the kit's floor. Until they are on Packagist, the versions there need PHP 8.4: raise the platform first (see [Requirements](../../README.md#requirements)) or wait for the releases.

Three packages together give each workspace an AI assistant that pays for itself:

- [Filament Agents](https://github.com/packstub/filament-agents) puts a chat in the app panel (the **Ask** button in the topbar, a slide-over, a page of past chats). The assistant works through tools you write, with the person's own permissions, inside their workspace.
- [Agents for Laravel](https://github.com/packstub/agents) is the engine under it: the turns on the queue, the provider through laravel/ai, budgets and limits, a record of what every answer cost.
- [Credits for Laravel](https://github.com/packstub/credits) meters what the answers cost: each plan gives the workspace a monthly allowance of AI credits, each answer is charged by its tokens, and the assistant says so politely when the month's credits are spent.

## What you need

- The three packages above. They bring `laravel/ai` and `laravel/mcp`; the kit already has `laravel/sanctum`.
- A provider key: `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, `GEMINI_API_KEY` or `XAI_API_KEY`, with `AGENT_PROVIDER` naming it. Without a key the chat hides itself.
- A queue worker in production (`QUEUE_CONNECTION=database` or Redis, `php artisan queue:work`): every answer is a queued job. With the kit's `sync` default it runs inside the request, fine while you try it.
- A panel theme, for the chat's styles (step 7).
- MySQL, MariaDB or Postgres in production. On SQLite, Credits needs PHP 8.4 for concurrent writers (`transaction_mode`).

## 1. Install

```bash run
composer require packstub/filament-agents:^1.19 packstub/credits:^1.1
php artisan vendor:publish --tag=packstub-credits-config
php artisan migrate
```

Both packages run their migrations from `vendor`: the chat tables (`agent_conversations`, `agent_turns`…) and the ledger (`credit_wallets`, `credit_grants`, `credit_holds`, `credit_transactions`, `credit_allocations`).

The engine also serves the tools to Claude Code, Cursor and other MCP clients. The kit's API tokens belong to workspaces, while MCP tokens belong to people, so keep that off until you want it (see [Going further](#going-further)). In `.env`, and `.env.example`:

```dotenv append=.env
# Agents: the in-panel chat only; no MCP endpoint.
AGENT_MCP_ENABLED=false
```

## 2. The assistant and its first tool

The assistant, `app/Ai/Agents/Assistant.php`:

```php file=app/Ai/Agents/Assistant.php
<?php

namespace App\Ai\Agents;

use Packstub\Agents\Ai\Agent;

/**
 * The workspace's assistant, in the app panel. It works through the tools
 * listed on the plugin (app/Filament/Plugins.php), inside the current
 * workspace and with the person's own permissions.
 */
class Assistant extends Agent
{
    protected function persona(): string
    {
        return 'You are the assistant of this workspace. You live inside the app and work with its data through tools.';
    }

    protected function domain(): string
    {
        return <<<'PROMPT'
        - A workspace is a team's account: its members, its plan and its projects.
        - A project has a name and a status: Active, Paused or Done.
        PROMPT;
    }
}
```

A tool is a `laravel/mcp` tool class. This one lists the workspace's projects, `app/Mcp/Tools/ListProjects.php`:

```php file=app/Mcp/Tools/ListProjects.php
<?php

namespace App\Mcp\Tools;

use App\Filament\App\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Packstub\Agents\Facades\Agents;
use Packstub\Agents\Mcp\AgentTool;

#[IsReadOnly]
#[Description('List the projects of this workspace, newest change first, optionally by status (active, paused, done). Returns compact rows with a url.')]
class ListProjects extends AgentTool
{
    /** Every member of the workspace may see its projects. */
    protected ?string $ability = null;

    protected function run(Request $request): array
    {
        $workspace = Agents::tenant();

        if (! $workspace instanceof Workspace) {
            return ['total' => 0, 'rows' => []];
        }

        $query = Project::query()
            ->whereBelongsTo($workspace)
            ->when($request->get('status'), fn ($query, string $status) => $query->where('status', $status));

        return [
            'total' => $query->count(),
            'rows' => $query->latest('updated_at')->limit($this->limit($request))->get()
                ->map(fn (Project $project): array => [
                    'name' => $project->name,
                    'status' => $project->status->value,
                    'updated' => $project->updated_at?->toDateString(),
                    'url' => ProjectResource::getUrl(panel: 'app', tenant: $workspace),
                ])->all(),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('active, paused or done.'),
            'limit' => $schema->integer()->description('Max rows, default 20.'),
        ];
    }
}
```

`php artisan packstub-agents:tool <Name>` writes the next one; `--write` makes a tool that changes data, which the person approves in the chat first. See [Tools](https://github.com/packstub/agents/blob/main/docs/tools.md).

## 3. AI credits as a plan allowance

A plan feature says how many credits a workspace gets each month. In `config/packstub-features.php`:

```php in=config/packstub-features.php after="'features' => [|'seats' =>"
'ai_credits' => ['label' => 'AI credits', 'type' => 'limit', 'default' => 0, 'unit' => 'credits a month', 'group' => 'Workspace', 'description' => 'What the assistant may spend each month.'],
```

```php in=config/packstub-features.php after="'plans' => [|'free' =>|'seats' =>"
'ai_credits' => 200,
```

```php in=config/packstub-features.php after="'plans' => [|'pro' =>|'seats' =>"
'ai_credits' => 20_000,
```

```php in=config/packstub-features.php after="'plans' => [|'business' =>|'seats' =>"
'ai_credits' => 100_000,
```

What an answer costs, in `config/packstub-credits.php` (one credit per thousand tokens read, five per thousand written; set them from your provider's prices and your margin):

```php in=config/packstub-credits.php replace="'meters' => [" through="],"
'meters' => [
    'ai.input_tokens' => ['credits' => 1, 'per' => 1_000],
    'ai.output_tokens' => ['credits' => 5, 'per' => 1_000],
],
```

The workspace holds the credits, as it holds the Stripe subscription. In `app/Models/Workspace.php`:

```php use=app/Models/Workspace.php
use Packstub\Credits\Concerns\HasCredits;
```

```php in=app/Models/Workspace.php after="class Workspace extends Model|use HasApiTokens;"

// AI credits (Credits for Laravel): the plan's monthly allowance, spent by the assistant.
use HasCredits;
```

Credits grants each month's allowance the first time the wallet is touched in that month, no cron needed, and a plan change applies at once. In `app/Providers/AppServiceProvider.php`:

```php use=app/Providers/AppServiceProvider.php
use Packstub\Credits\Facades\Credits;
use Packstub\Credits\Support\Allowance;
use Packstub\Features\Facades\Features;
```

```php in=app/Providers/AppServiceProvider.php after="function boot()|Model::shouldBeStrict("

// Each workspace's monthly AI credits come from its plan (the `ai_credits` feature).
Credits::allowancesUsing(fn (Workspace $workspace, string $wallet): array => ($credits = (int) Features::limit('ai_credits', $workspace)) > 0
    ? [Allowance::monthly($credits)]
    : []);
```

**Your plan** shows what is used this month, in `app/Support/Plans.php`:

```php in=app/Support/Plans.php after="->usage('seats',"
->usage('ai_credits', fn (?Workspace $workspace): int => $workspace ? max(0, (int) Features::limit('ai_credits', $workspace) - $workspace->credits()->available()) : 0)
```

```php use=app/Support/Plans.php
use Packstub\Features\Facades\Features;
```

Charge every answer, and refuse a question once the month's credits are spent, with an agent middleware, `app/Ai/Middleware/ChargeAiCredits.php`:

```php file=app/Ai/Middleware/ChargeAiCredits.php
<?php

namespace App\Ai\Middleware;

use App\Models\Workspace;
use Closure;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\PendingStep;
use Packstub\Agents\Exceptions\TurnRefused;
use Packstub\Agents\Facades\Agents;

/**
 * The assistant spends the workspace's AI credits: a question needs some
 * left, and each round-trip to the model is charged by its tokens
 * (config/packstub-credits.php). An answer already under way finishes even
 * when it runs the balance to zero.
 */
class ChargeAiCredits
{
    public function handle(PendingStep $step, Closure $next): mixed
    {
        $workspace = Agents::tenant();

        if (! $workspace instanceof Workspace) {
            return $next($step);
        }

        if ($step->isFirstStep() && $workspace->credits()->available() <= 0) {
            throw new TurnRefused('This workspace has used its AI credits for this month. They renew next month, or sooner on a bigger plan.');
        }

        return $next($step)->then(function (StepResponse $response) use ($workspace): void {
            $workspace->credits()->meter([
                'ai.input_tokens' => $response->usage->inputTokens,
                'ai.output_tokens' => $response->usage->outputTokens,
            ], reason: 'AI assistant');
        });
    }
}
```

For a stricter hold (reserve an estimate before the call, settle after), see Credits' [Holds and AI calls](https://github.com/packstub/credits/blob/main/docs/holds.md). Selling extra credits as one-off Stripe payments is in [Selling credits](https://github.com/packstub/credits/blob/main/docs/selling-credits.md).

## 4. Register the plugin

In `app/Filament/Plugins.php`:

```php use=app/Filament/Plugins.php
use App\Ai\Agents\Assistant;
use App\Ai\Middleware\ChargeAiCredits;
use App\Mcp\Tools\ListProjects;
use Packstub\Agents\AgentsPlugin;
```

The chat in the app panel, without the MCP token page:

```php in=app/Filament/Plugins.php before="function app()|];"
// The AI assistant: the Ask button, the chat and past chats, in the current workspace.
AgentsPlugin::make()
    ->name('Assistant')
    ->agent(Assistant::class)
    ->tools([ListProjects::class])
    ->middleware([ChargeAiCredits::class])
    ->agentAccess(false),
```

and in the admin panel, every workspace's turns (who asked, the model, tokens, cost) and the limits per workspace, without a chat:

```php in=app/Filament/Plugins.php before="function admin()|];"
// AI turns and limits for every workspace.
AgentsPlugin::make()
    ->agent(Assistant::class)
    ->tools([ListProjects::class])
    ->chat(false)
    ->agentAccess(false)
    ->limits(authorize: fn (): bool => (bool) auth()->user()?->is_admin)
    ->turnLog(),
```

The engine's own limits (answers per day, tokens per day and month per workspace and per person) still apply under the credits, as a ceiling on what one workspace can cost you; edit them on **AI limits** in `/admin`.

## 5. Say where its tables live

The chats and the ledger stay on the central database in this kit (see below). In `centralTables()` in `tests/Feature/TableRulesTest.php`:

```php in=tests/Feature/TableRulesTest.php before="function centralTables()|];"
// Agents: chats, turns and limits; each turn carries its workspace in `tenant`.
'agent_limits' => 'the operator\'s limits',
'agent_conversations' => 'a person\'s chats; their turns name the workspace',
'agent_conversation_messages' => 'chats',
'agent_conversation_summaries' => 'chats',
'agent_conversation_classifications' => 'chats',
'agent_message_feedback' => 'chats',
'agent_answer_versions' => 'chats',
'agent_pinned_conversations' => 'chats',
'agent_turns' => 'chats, with the workspace in `tenant`',
'agent_tool_calls' => 'chats, with the workspace in `tenant`',
// Credits: the ledger is billing, like the Stripe subscriptions.
'credit_wallets' => 'billing: the workspace is the holder',
'credit_grants' => 'billing',
'credit_holds' => 'billing',
'credit_transactions' => 'billing',
'credit_allocations' => 'billing',
```

## 6. Delete chats and credits with their workspace or person

Create `app/Listeners/ForgetAssistantData.php`:

```php file=app/Listeners/ForgetAssistantData.php
<?php

namespace App\Listeners;

use App\Events\AccountDeleting;
use App\Events\WorkspaceDeleting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Packstub\Agents\Models\AgentLimit;

/**
 * A deleted workspace's chats, turns, limits and AI credits go with it; a
 * deleted person's chats, turns and limits go with them.
 */
class ForgetAssistantData
{
    public function handle(WorkspaceDeleting|AccountDeleting $event): void
    {
        if ($event instanceof WorkspaceDeleting) {
            $key = (string) $event->workspace->getKey();

            $this->deleteConversations(DB::table('agent_turns')->where('tenant', $key)->distinct()->pluck('conversation_id'));
            DB::table('agent_turns')->where('tenant', $key)->delete();
            DB::table('agent_tool_calls')->where('tenant', $key)->delete();
            AgentLimit::query()->where('scope', 'tenant')->where('scope_id', $key)->delete();
            $this->deleteWallets($event->workspace->creditWallets()->pluck('id'));

            return;
        }

        $participant = fn ($query) => $query->where('participant_type', $event->user->getMorphClass())->where('participant_id', $event->user->getKey());

        $this->deleteConversations(DB::table('agent_conversations')->where($participant)->pluck('id'));
        DB::table('agent_turns')->where($participant)->delete();
        DB::table('agent_tool_calls')->where($participant)->delete();
        DB::table('agent_message_feedback')->where('user_id', $event->user->getKey())->delete();
        AgentLimit::query()->where('scope', 'user')->where('scope_id', (string) $event->user->getKey())->delete();
    }

    /**
     * @param  Collection<int, string>  $ids
     */
    private function deleteConversations(Collection $ids): void
    {
        $messages = DB::table('agent_conversation_messages')->whereIn('conversation_id', $ids)->pluck('id');

        DB::table('agent_message_feedback')->whereIn('message_id', $messages)->delete();

        foreach (['agent_conversation_messages', 'agent_conversation_summaries', 'agent_conversation_classifications', 'agent_answer_versions', 'agent_pinned_conversations', 'agent_tool_calls', 'agent_turns'] as $table) {
            DB::table($table)->whereIn('conversation_id', $ids)->delete();
        }

        DB::table('agent_conversations')->whereIn('id', $ids)->delete();
    }

    /**
     * @param  Collection<int, int>  $wallets
     */
    private function deleteWallets(Collection $wallets): void
    {
        $transactions = DB::table('credit_transactions')->whereIn('wallet_id', $wallets)->pluck('id');

        DB::table('credit_allocations')->whereIn('transaction_id', $transactions)->delete();

        foreach (['credit_transactions', 'credit_holds', 'credit_grants'] as $table) {
            DB::table($table)->whereIn('wallet_id', $wallets)->delete();
        }

        DB::table('credit_wallets')->whereIn('id', $wallets)->delete();
    }
}
```

## 7. The chat's styles

Filament 5 builds a plugin's styles into the panel's own theme, and the kit's panels use Filament's default one. Make a theme for the app panel once:

```bash
php artisan make:filament-theme app
```

Add `resources/css/filament/app/theme.css` to `input` in `vite.config.js`, call `->viteTheme('resources/css/filament/app/theme.css')` in `AppPanelProvider`, and point the theme at the plugin's views:

```css
@source '../../../../vendor/packstub/filament-agents/resources/views';
```

Then `npm run build`. The tests render the panel too, so CI needs the built assets from then on: add `npm ci` and `npm run build` before the tests in `.github/workflows/tests.yml`.

## 8. Schedule the housekeeping

In `routes/console.php`:

```php append=routes/console.php
// AI turns older than AGENT_KEEP_TURNS_DAYS (90), and AI credit lots that ran out.
Schedule::command('model:prune', ['--model' => [Packstub\Agents\Models\AgentTurn::class]])->daily();
Schedule::command('credits:expire')->hourly();
```

## 9. Test it

`tests/Feature/Modules/AssistantTest.php`. The model is faked; never call a provider from a test:

```php file=tests/Feature/Modules/AssistantTest.php
<?php

use App\Mcp\Tools\ListProjects;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\TextResponse;
use Packstub\Agents\Testing\AgentEval;
use Packstub\Features\Facades\Features;

beforeEach(fn () => config(['ai.providers.anthropic.key' => 'test-key']));

it('gives each workspace its plan\'s AI credits every month', function (string $plan, int $credits) {
    $workspace = workspace($plan);

    expect($workspace->credits()->available())->toBe($credits)
        ->and(Features::used('ai_credits', $workspace))->toBe(0);
})->with([
    ['free', 200],
    ['pro', 20_000],
    ['business', 100_000],
]);

it('answers from the workspace\'s own projects and charges the answer', function () {
    $workspace = workspace('pro');
    Project::factory()->for($workspace)->create(['name' => 'Launch']);
    Project::factory()->for(workspace())->create(['name' => 'Not yours']);
    $member = inWorkspace($workspace, memberOf($workspace, 'member'));

    $result = AgentEval::as($member)->in($workspace)
        ->expecting([
            new ToolCall('c1', 'list-projects', []),
            new TextResponse('One project: Launch.', new TextUsage(inputTokens: 10_000, outputTokens: 2_000), new Meta('anthropic', 'test')),
        ])
        ->ask('Which projects do we have?')
        ->assertOk()
        ->assertCalled('list-projects');

    expect(json_encode($result->toolCalls()))->toContain('Launch')->not->toContain('Not yours')
        ->and($workspace->credits()->available())->toBeLessThan(20_000);
});

it('refuses a question once the month\'s credits are spent', function () {
    $workspace = workspace('free');
    $workspace->credits()->spend(200, 'Used up');
    $member = inWorkspace($workspace);

    AgentEval::as($member)->in($workspace)
        ->expecting(['Never sent.'])
        ->ask('Hello?')
        ->assertRefused();
});

it('forgets a deleted workspace\'s chats and credits', function () {
    $owner = User::factory()->create();
    $workspace = workspace('pro', $owner);
    inWorkspace($workspace, $owner);

    AgentEval::as($owner)->in($workspace)->expecting(['Hi.'])->ask('Hello?')->assertOk();

    $owner->deleteAccount();

    expect(DB::table('agent_conversations')->count())->toBe(0)
        ->and(DB::table('agent_turns')->count())->toBe(0)
        ->and(DB::table('credit_wallets')->count())->toBe(0);
});
```

```bash run
composer lint
composer test
```

## A database per workspace later

Two tables groups, two answers:

- **The ledger stays central**, with billing: the workspace is the wallet's holder, like the Stripe customer.
- **The chats move into each workspace's database**: publish Agents' migrations, keep `create_agent_limits_table` with the central ones, move the rest into `database/migrations/workspace`, and set `run_migrations` to `false` and `AGENT_LIMITS_CONNECTION` to your central connection. The engine enters the workspace through Filament's `TenantSet` event, so turns and tools run on its database. See the engine's [Tenancy](https://github.com/packstub/agents/blob/main/docs/tenancy.md#database-per-tenant).

## Going further

- **MCP clients** (Claude Code, Cursor): the engine serves the same tools over MCP with personal tokens. Turn it on with `AGENT_MCP_ENABLED=true`, `mcp.path` set to `mcp/{tenant}`, `HasApiTokens` on `User` and `->agentAccess()` on the plugin. The kit's `/api/v1` expects workspace tokens, so check that a person's token is refused there before you ship it. See [MCP clients](https://github.com/packstub/filament-agents/blob/main/docs/mcp-clients.md).
- **A workspace's own key**: `->credentialsUsing()` lets a workspace bring its own provider key, which then pays instead of your credits. See [Tenancy](https://github.com/packstub/filament-agents/blob/main/docs/tenancy.md#a-workspaces-own-key).

## Removing it

Delete the plugin lines and imports from `app/Filament/Plugins.php`, `app/Ai`, `app/Mcp`, `HasCredits` from `Workspace`, the allowance in `AppServiceProvider`, the `ai_credits` feature, plan values and `usage()` line, the two schedule lines, `AGENT_MCP_ENABLED`, `app/Listeners/ForgetAssistantData.php`, `tests/Feature/Modules/AssistantTest.php` and the lines in `centralTables()`, then:

```bash
composer remove packstub/filament-agents packstub/agents packstub/credits
rm config/packstub-credits.php
```

Drop the tables with a migration of your own.
