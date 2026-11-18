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
    public static function getNavigationGroup(): ?string
    {
        return __('app.navigation.workspace');
    }

    use InteractsWithTable;

    public static function getNavigationLabel(): string
    {
        return __('api_tokens.title');
    }

    public function getTitle(): string
    {
        return __('api_tokens.title');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'api-tokens';

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
        return __('api_tokens.subheading', ['url' => url('/api/v1')]);
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
            ->label(__('api_tokens.actions.create'))
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => $this->canCreateTokens())
            ->visible(fn (): bool => $this->canCreateTokens())
            ->modalHeading(__('api_tokens.actions.create'))
            ->modalSubmitActionLabel(__('api_tokens.actions.create_submit'))
            ->schema([
                TextInput::make('name')
                    ->label(__('api_tokens.fields.name'))
                    ->helperText(__('api_tokens.fields.name_help'))
                    ->required()
                    ->maxLength(100),
                CheckboxList::make('abilities')
                    ->label(__('api_tokens.fields.abilities'))
                    ->options(ApiToken::abilityOptions())
                    ->default(['projects:read'])
                    ->required(),
                Select::make('expires_in')
                    ->label(__('api_tokens.fields.expires'))
                    ->options(array_combine($expiries = ['30', '90', '365', 'never'], array_map(fn (string $key): string => __("api_tokens.expires_in.{$key}"), $expiries)))
                    ->default('90')
                    ->selectablePlaceholder(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $workspace = $this->workspace();
                $abilities = array_values(array_intersect(ApiToken::ABILITIES, $data['abilities']));
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
            ->label(__('api_tokens.actions.upgrade'))
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
                    ->label(__('api_tokens.columns.name'))
                    ->searchable(),
                TextColumn::make('abilities')
                    ->label(__('api_tokens.columns.abilities'))
                    ->formatStateUsing(fn (string $state): string => ApiToken::abilityLabel($state))
                    ->badge(),
                TextColumn::make('created_by_name')
                    ->label(__('api_tokens.columns.created_by'))
                    ->placeholder(__('api_tokens.columns.created_by_empty')),
                TextColumn::make('last_used_at')
                    ->label(__('api_tokens.columns.last_used'))
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder(__('api_tokens.columns.never')),
                TextColumn::make('expires_at')
                    ->label(__('api_tokens.columns.expires'))
                    ->date()
                    ->placeholder(__('api_tokens.columns.never'))
                    ->color(fn (ApiToken $record): ?string => $record->expires_at?->isPast() ? 'danger' : null),
                TextColumn::make('created_at')
                    ->label(__('api_tokens.columns.created'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('api_tokens.actions.revoke'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ApiToken $record): string => __('api_tokens.actions.revoke_heading', ['name' => $record->name]))
                    ->modalDescription(__('api_tokens.actions.revoke_description'))
                    ->authorize(fn (): bool => static::canAccess())
                    ->action(function (ApiToken $record): void {
                        // The query is the workspace's own tokens, so a record from another workspace never gets here.
                        $record->delete();

                        AuditEvent::record($this->workspace(), AuditEventType::ApiTokenRevoked, ['name' => $record->name]);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('api_tokens.empty.heading'))
            ->emptyStateDescription(fn (): string => $this->hasApiAccess()
                ? __('api_tokens.empty.with_access')
                : __('api_tokens.empty.without_access'));
    }

    protected function workspace(): Workspace
    {
        return Filament::getTenant();
    }
}
