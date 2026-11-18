<?php

namespace App\Filament\App\Pages\Auth;

use App\Models\User;
use App\Models\Workspace;
use App\Support\SocialLogin;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use LogicException;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Stripe\Exception\ApiErrorException;

/**
 * Filament's Profile page, plus "Download your data" and "Delete account" at the bottom.
 */
class EditProfile extends BaseEditProfile
{
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
            Section::make('Connected accounts')
                ->description(fn (): string => $this->getUser()->hasPassword()
                    ? 'Sign in with these as well as your password.'
                    : 'You sign in with these. Choose a password above to have one more way in.')
                ->compact()
                ->secondary()
                ->visible(fn (): bool => $this->isOwnAccount() && (SocialLogin::enabled() || $this->getUser()->socialAccounts()->exists()))
                ->schema(fn (): array => [Actions::make($this->connectedAccountActions())]),
            Section::make('Your data')
                ->description('Your account, your workspaces and roles, your notifications and every support login into your account, as one JSON file.')
                ->compact()
                ->secondary()
                ->visible(fn (): bool => $this->isOwnAccount())
                ->schema([
                    Actions::make([$this->downloadDataAction()]),
                ]),
            Section::make('Delete account')
                ->description('Your account, and every workspace where you are the only member, go for good.')
                ->compact()
                ->secondary()
                ->visible(fn (): bool => $this->isOwnAccount())
                ->schema([
                    Actions::make([$this->deleteAccountAction()]),
                ]),
        ]);
    }

    /**
     * Connect for each provider that is set up, Disconnect for each connected account.
     *
     * @return list<Action>
     */
    protected function connectedAccountActions(): array
    {
        $connected = $this->getUser()->socialAccounts()->get()->keyBy('provider');

        return collect(SocialLogin::PROVIDERS)
            ->filter(fn (string $label, string $provider): bool => $connected->has($provider) || SocialLogin::isEnabled($provider))
            ->map(fn (string $label, string $provider): Action => $connected->has($provider)
                ? $this->disconnectAction()->arguments(['provider' => $provider, 'email' => $connected[$provider]->email])
                : Action::make("connect_{$provider}")
                    ->label("Connect {$label}")
                    ->color('gray')
                    ->url(route('social.redirect', $provider)))
            ->values()
            ->all();
    }

    /**
     * Disconnect one connected account (the `provider` argument). Never the
     * last way to sign in: someone without a password keeps at least one.
     */
    public function disconnectAction(): Action
    {
        $isLast = fn (): bool => $this->getUser()->signInMethodCount() <= 1;
        $label = fn (array $arguments): string => SocialLogin::PROVIDERS[$arguments['provider'] ?? ''] ?? 'account';

        return Action::make('disconnect')
            ->label(fn (array $arguments): string => 'Disconnect '.$label($arguments).(filled($arguments['email'] ?? null) ? " ({$arguments['email']})" : ''))
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('gray')
            ->disabled($isLast)
            ->tooltip(fn (): ?string => $isLast() ? 'Choose a password first: this is your only way to sign in.' : null)
            ->authorize(fn (): bool => $this->isOwnAccount())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Disconnect '.$label($arguments).'?')
            ->modalDescription(fn (array $arguments): string => 'You will no longer be able to sign in with '.$label($arguments).'.')
            ->action(function (Action $action, array $arguments) use ($isLast, $label): void {
                if ($isLast()) {
                    $action->halt();
                }

                $this->getUser()->socialAccounts()->where('provider', $arguments['provider'] ?? null)->delete();

                Notification::make()->success()->title($label($arguments).' is disconnected.')->send();
            });
    }

    /**
     * Filament asks for the current password before a new password or e-mail
     * address is saved. Someone who signed up with Google or GitHub has none
     * to give; their new address still has to be confirmed from its inbox.
     */
    protected function getCurrentPasswordFormComponent(): Component
    {
        $component = parent::getCurrentPasswordFormComponent();

        return $this->getUser()->hasPassword() ? $component : $component->hidden();
    }

    /**
     * Everything the app holds about this person (App\Support\DataExport::forUser()),
     * downloaded from App\Http\Controllers\PersonalDataExportController.
     */
    public function downloadDataAction(): Action
    {
        return Action::make('downloadData')
            ->label('Download your data')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->authorize(fn (): bool => $this->isOwnAccount())
            ->url(fn (): string => route('exports.personal'));
    }

    /**
     * After the current password. Someone who is the only owner of a
     * workspace others still use hands it over (Members, "Transfer
     * ownership") or deletes it first, so nobody is left without an owner.
     * Never during a support login: the admin is not the account's owner.
     */
    public function deleteAccountAction(): Action
    {
        return Action::make('deleteAccount')
            ->label('Delete account')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize(fn (): bool => $this->isOwnAccount())
            ->requiresConfirmation()
            ->modalHeading('Delete your account?')
            ->modalDescription(fn (): string => $this->blockingWorkspaces()
                ? 'First hand over ownership of these workspaces under Members, or delete them: '.implode(', ', $this->blockingWorkspaces()).'.'
                : 'You leave every workspace you share with others. Workspaces where you are the only member are deleted, and their subscriptions cancelled. This cannot be undone.')
            ->modalSubmitAction(fn ($action) => $this->blockingWorkspaces() ? false : $action->label('Delete account'))
            ->schema(fn (): array => match (true) {
                (bool) $this->blockingWorkspaces() => [],
                // Someone who signs in with Google or GitHub only confirms with their address.
                ! $this->getUser()->hasPassword() => [
                    TextInput::make('email')
                        ->label('Type your e-mail address to confirm')
                        ->in([$this->getUser()->email])
                        ->validationMessages(['in' => 'That is not your e-mail address.'])
                        ->required(),
                ],
                default => [
                    TextInput::make('password')
                        ->label('Your password')
                        ->password()
                        ->autocomplete('current-password')
                        ->currentPassword(guard: Filament::getAuthGuard())
                        ->required(),
                ],
            })
            ->action(function (Action $action) {
                if ($this->blockingWorkspaces()) {
                    $action->halt();
                }

                try {
                    $this->getUser()->deleteAccount();
                } catch (LogicException) {
                    // A workspace became theirs alone to own while the dialog was open.
                    Notification::make()
                        ->danger()
                        ->title('Hand over or delete the workspaces you are the only owner of first')
                        ->send();

                    $action->halt();
                } catch (ApiErrorException $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title('A subscription could not be cancelled, so your account was kept')
                        ->body('Please try again in a moment.')
                        ->send();

                    $action->halt();
                }

                // Not logout(): it saves a new remember token on the user, which would put the deleted row back.
                Filament::auth()->logoutCurrentDevice();
                session()->invalidate();
                session()->regenerateToken();

                return redirect()->route('home');
            });
    }

    /**
     * The names of the workspaces that stop the account from being deleted.
     *
     * @return list<string>
     */
    protected function blockingWorkspaces(): array
    {
        return $this->getUser()->workspacesBlockingDeletion()->map(fn (Workspace $workspace): string => $workspace->name)->values()->all();
    }

    /**
     * The person themselves, not support signed in as them.
     */
    protected function isOwnAccount(): bool
    {
        return $this->getUser() instanceof User && ! AccountSwitcher::isImpersonating();
    }
}
