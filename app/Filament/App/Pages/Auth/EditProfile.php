<?php

namespace App\Filament\App\Pages\Auth;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Locales;
use App\Support\SocialLogin;
use App\Support\Uploads;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getAvatarFormComponent(),
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getLocaleFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    /**
     * The profile picture, in the person's own folder on the uploads disk
     * (config/uploads.php). User removes the file it replaces, and the
     * folder goes with the account.
     */
    protected function getAvatarFormComponent(): Component
    {
        return FileUpload::make('avatar_path')
            ->label(__('profile.avatar'))
            ->image()
            ->avatar()
            ->disk(Uploads::diskName())
            ->visibility(config('uploads.visibility'))
            ->directory(fn (): string => Uploads::userDirectory($this->getUser()).'/avatar')
            ->acceptedFileTypes(config('uploads.image_types'))
            ->maxSize(config('uploads.max_size'));
    }

    /**
     * The language of the panel and of this person's e-mails (`locales` in config/app.php).
     */
    protected function getLocaleFormComponent(): Component
    {
        return Select::make('locale')
            ->label(__('app.language'))
            ->options(Locales::all())
            ->placeholder(fn (): string => Locales::all()[config('app.locale')] ?? config('app.locale'))
            ->in(array_keys(Locales::all()));
    }

    /**
     * A new language applies from the next page: open this one again in it.
     */
    protected function afterSave(): void
    {
        if ($this->getUser()->wasChanged('locale')) {
            $this->redirect(static::getUrl());
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
            Section::make(__('profile.connected.heading'))
                ->description(fn (): string => $this->getUser()->hasPassword()
                    ? __('profile.connected.with_password')
                    : __('profile.connected.without_password'))
                ->compact()
                ->secondary()
                ->visible(fn (): bool => $this->isOwnAccount() && (SocialLogin::enabled() || $this->getUser()->socialAccounts()->exists()))
                ->schema(fn (): array => [Actions::make($this->connectedAccountActions())]),
            Section::make(__('profile.data.heading'))
                ->description(__('profile.data.description'))
                ->compact()
                ->secondary()
                ->visible(fn (): bool => $this->isOwnAccount())
                ->schema([
                    Actions::make([$this->downloadDataAction()]),
                ]),
            Section::make(__('profile.delete.heading'))
                ->description(__('profile.delete.description'))
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
                    ->label(__('profile.connected.connect', ['provider' => $label]))
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
        $label = fn (array $arguments): string => SocialLogin::PROVIDERS[$arguments['provider'] ?? ''] ?? __('profile.connected.fallback_provider');

        return Action::make('disconnect')
            ->label(fn (array $arguments): string => filled($arguments['email'] ?? null)
                ? __('profile.connected.disconnect_with_email', ['provider' => $label($arguments), 'email' => $arguments['email']])
                : __('profile.connected.disconnect', ['provider' => $label($arguments)]))
            ->icon(Heroicon::OutlinedLinkSlash)
            ->color('gray')
            ->disabled($isLast)
            ->tooltip(fn (): ?string => $isLast() ? __('profile.connected.only_way') : null)
            ->authorize(fn (): bool => $this->isOwnAccount())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('profile.connected.disconnect_heading', ['provider' => $label($arguments)]))
            ->modalDescription(fn (array $arguments): string => __('profile.connected.disconnect_description', ['provider' => $label($arguments)]))
            ->action(function (Action $action, array $arguments) use ($isLast, $label): void {
                if ($isLast()) {
                    $action->halt();
                }

                $this->getUser()->socialAccounts()->where('provider', $arguments['provider'] ?? null)->delete();

                Notification::make()->success()->title(__('profile.connected.disconnected', ['provider' => $label($arguments)]))->send();
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
            ->label(__('profile.data.download'))
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
            ->label(__('profile.delete.action'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize(fn (): bool => $this->isOwnAccount())
            ->requiresConfirmation()
            ->modalHeading(__('profile.delete.modal_heading'))
            ->modalDescription(fn (): string => $this->blockingWorkspaces()
                ? __('profile.delete.blocked', ['workspaces' => implode(', ', $this->blockingWorkspaces())])
                : __('profile.delete.modal_description'))
            ->modalSubmitAction(fn ($action) => $this->blockingWorkspaces() ? false : $action->label(__('profile.delete.action')))
            ->schema(fn (): array => match (true) {
                (bool) $this->blockingWorkspaces() => [],
                // Someone who signs in with Google or GitHub only confirms with their address.
                ! $this->getUser()->hasPassword() => [
                    TextInput::make('email')
                        ->label(__('profile.delete.confirm_email'))
                        ->in([$this->getUser()->email])
                        ->validationMessages(['in' => __('profile.delete.wrong_email')])
                        ->required(),
                ],
                default => [
                    TextInput::make('password')
                        ->label(__('profile.delete.password'))
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
                        ->title(__('profile.delete.hand_over_first'))
                        ->send();

                    $action->halt();
                } catch (ApiErrorException $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title(__('profile.delete.stripe_failed'))
                        ->body(__('profile.delete.try_again'))
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
