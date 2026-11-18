<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Packstub\AccountSwitcher\Filament\Actions\ImpersonateAction;

/**
 * Everyone with an account, and the way into a customer's panel: "Impersonate"
 * signs the admin in as that user (see App\Support\SupportLogins).
 */
class UserResource extends Resource
{
    public static function getNavigationGroup(): ?string
    {
        return __('app.navigation.customers');
    }

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getModelLabel(): string
    {
        return __('admin.users.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.users.plural_model');
    }

    public static function getTitleCaseModelLabel(): string
    {
        return __('admin.users.title');
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return __('admin.users.plural_title');
    }

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        // One query for the workspace counts of the whole page.
        return parent::getEloquentQuery()->withCount('joinedTeams');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.users.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('admin.users.columns.email'))
                    ->searchable(),
                TextColumn::make('joined_teams_count')
                    ->label(__('admin.users.columns.workspaces')),
                IconColumn::make('is_admin')
                    ->label(__('admin.users.columns.admin'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.users.columns.signed_up'))
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ImpersonateAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
