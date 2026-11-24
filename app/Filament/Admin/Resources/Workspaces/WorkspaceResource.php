<?php

namespace App\Filament\Admin\Resources\Workspaces;

use App\Filament\Admin\Resources\AuditEvents\AuditEventResource;
use App\Filament\Admin\Resources\Workspaces\Pages\ManageWorkspaces;
use App\Models\Workspace;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Packstub\Features\Facades\Features;
use Packstub\Features\Support\Plan;

/**
 * Every workspace with its plan and subscription. "Change plan" is for a
 * workspace you want on a plan without a subscription (a partner, a pilot);
 * for a single limit, use Feature overrides instead. The table reads the
 * workspace's own row, its members and its subscription only: what a
 * workspace owns (projects) may one day live in another database.
 */
class WorkspaceResource extends Resource
{
    public static function getNavigationGroup(): ?string
    {
        return __('ui_app.navigation.customers');
    }

    protected static ?string $model = Workspace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getModelLabel(): string
    {
        return __('ui_admin.workspaces.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui_admin.workspaces.plural_model');
    }

    public static function getTitleCaseModelLabel(): string
    {
        return __('ui_admin.workspaces.title');
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return __('ui_admin.workspaces.plural_title');
    }

    public static function getEloquentQuery(): Builder
    {
        // One query each for the subscriptions and the member counts of the whole page.
        return parent::getEloquentQuery()->with('subscriptions')->withCount('members');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('ui_admin.workspaces.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan')
                    ->label(__('ui_admin.workspaces.columns.plan'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Features::planDefinition($state)?->label ?? $state),
                TextColumn::make('subscription')
                    ->label(__('ui_admin.workspaces.columns.subscription'))
                    ->state(fn (Workspace $record): ?string => $record->currentSubscription()?->stripe_status)
                    ->placeholder(__('ui_admin.workspaces.columns.none')),
                TextColumn::make('members_count')
                    ->label(__('ui_admin.workspaces.columns.members')),
                TextColumn::make('created_at')
                    ->label(__('ui_admin.workspaces.columns.created'))
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('activity')
                    ->label(__('ui_admin.workspaces.actions.activity'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('gray')
                    ->url(fn (Workspace $record): string => AuditEventResource::getUrl('index', ['filters' => ['workspace' => ['value' => $record->getKey()]]])),
                Action::make('changePlan')
                    ->label(__('ui_admin.workspaces.actions.change_plan'))
                    ->icon(Heroicon::OutlinedSparkles)
                    ->fillForm(fn (Workspace $record): array => ['plan' => $record->plan])
                    ->schema([
                        Select::make('plan')
                            ->label(__('ui_admin.workspaces.fields.plan'))
                            ->options(fn (): array => array_map(fn (Plan $plan): string => $plan->label, Features::plans()))
                            ->required()
                            ->helperText(__('ui_admin.workspaces.fields.plan_help')),
                    ])
                    ->action(fn (Workspace $record, array $data) => $record->changePlan($data['plan']))
                    ->successNotificationTitle(__('ui_admin.workspaces.plan_changed')),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkspaces::route('/'),
        ];
    }
}
