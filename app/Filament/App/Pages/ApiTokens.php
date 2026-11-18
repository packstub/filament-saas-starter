<?php

namespace App\Filament\App\Pages;

use App\Enums\AuditEventType;
use App\Models\ApiToken;
use App\Models\AuditEvent;
use App\Models\Workspace;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\Features\Facades\Features;
use UnitEnum;

/**
 * The workspace's API tokens, for owners and admins: create one with a name,
 * what it may do and when it expires, and revoke it. A token belongs to the
 * workspace and is shown once, right after it is created; only its hash is
 * stored. It is revoked when its creator no longer manages the workspace
 * (App\Listeners\RevokeApiTokensOfFormerManagers).
 *
 * The API itself is routes/api.php. Tokens need the plan's `api_access`
 * feature; without it the page offers an upgrade instead.
 */
class ApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'api-tokens';

    protected static ?string $title = 'API tokens';

    protected string $view = 'filament.app.pages.api-tokens';

    /** The token just created, shown once and then forgotten. Set by the server only. */
    #[Locked]
    public ?string $plainTextToken = null;

    public static function canAccess(): bool
    {
        $workspace = Filament::getTenant();

        return $workspace instanceof Workspace && (bool) Filament::auth()->user()?->canManageTeam($workspace);
    }

    public function getSubheading(): string
    {
        return 'Let your scripts and integrations use the API at '.url('/api/v1').' on behalf of this workspace.';
    }

    /**
     * Support signed in as a customer may revoke a token, never create one:
     * it would keep API access to the workspace after the support login ends.
     */
    public function canCreateTokens(): bool
    {
        return static::canAccess() && $this->hasApiAccess() && ! AccountSwitcher::isImpersonating();
    }

    public function hasApiAccess(): bool
    {
        return Features::active('api_access', $this->workspace());
    }

    protected function getHeaderActions(): array
    {
        return [$this->createAction(), $this->upgradeAction()];
    }

    public function createAction(): Action
    {
        return Action::make('create')
            ->label('New API token')
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => $this->canCreateTokens())
            ->visible(fn (): bool => $this->canCreateTokens())
            ->modalHeading('New API token')
            ->modalSubmitActionLabel('Create token')
            ->schema([
                TextInput::make('name')
                    ->helperText('What uses it: "Zapier", "Nightly import".')
                    ->required()
                    ->maxLength(100),
                CheckboxList::make('abilities')
                    ->label('It may')
                    ->options(ApiToken::ABILITIES)
                    ->default(['projects:read'])
                    ->required(),
                Select::make('expires_in')
                    ->label('Expires')
                    ->options(['30' => 'In 30 days', '90' => 'In 90 days', '365' => 'In a year', 'never' => 'Never'])
                    ->default('90')
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $workspace = $this->workspace();
                $abilities = array_values(array_intersect(array_keys(ApiToken::ABILITIES), $data['abilities']));
                $expiresAt = $data['expires_in'] === 'never' ? null : Carbon::now()->addDays((int) $data['expires_in']);

                $token = $workspace->createToken($data['name'], $abilities, $expiresAt);
                $user = Filament::auth()->user();
                $token->accessToken->forceFill(['created_by_id' => $user?->getKey(), 'created_by_name' => $user?->name])->save();

                AuditEvent::record($workspace, AuditEventType::ApiTokenCreated, ['name' => $data['name'], 'abilities' => $abilities]);

                $this->plainTextToken = $token->plainTextToken;
            });
    }

    public function upgradeAction(): Action
    {
        return Action::make('upgrade')
            ->label('Upgrade for API access')
            ->icon(Heroicon::OutlinedArrowUpCircle)
            ->visible(fn (): bool => ! $this->hasApiAccess() && Billing::canAccess())
            ->url(fn (): string => Billing::getUrl());
    }

    /**
     * Hide the token once it has been copied.
     */
    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->workspace()->tokens()->getQuery())
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('abilities')
                    ->label('May')
                    ->formatStateUsing(fn (string $state): string => ApiToken::ABILITIES[$state] ?? $state)
                    ->badge(),
                TextColumn::make('created_by_name')
                    ->label('Created by')
                    ->placeholder('Someone who left'),
                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('Never'),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never')
                    ->color(fn (ApiToken $record): ?string => $record->expires_at?->isPast() ? 'danger' : null),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ApiToken $record): string => "Revoke {$record->name}?")
                    ->modalDescription('Anything that uses this token stops working straight away. This cannot be undone.')
                    ->authorize(fn (): bool => static::canAccess())
                    ->action(function (ApiToken $record): void {
                        // The query is the workspace's own tokens, so a record from another workspace never gets here.
                        $record->delete();

                        AuditEvent::record($this->workspace(), AuditEventType::ApiTokenRevoked, ['name' => $record->name]);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No API tokens yet')
            ->emptyStateDescription(fn (): string => $this->hasApiAccess()
                ? 'Create one to call the API from a script or another app.'
                : 'API access comes with a paid plan.');
    }

    protected function workspace(): Workspace
    {
        return Filament::getTenant();
    }
}
