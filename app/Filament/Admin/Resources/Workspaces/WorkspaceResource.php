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
use UnitEnum;

/**
 * Every workspace with its plan and subscription. "Change plan" is for a
 * workspace you want on a plan without a subscription (a partner, a pilot);
 * for a single limit, use Feature overrides instead. The table reads the
 * workspace's own row, its members and its subscription only: what a
 * workspace owns (projects) may one day live in another database.
 */
class WorkspaceResource extends Resource
{
    protected static ?string $model = Workspace::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Features::planDefinition($state)?->label ?? $state),
                TextColumn::make('subscription')
                    ->state(fn (Workspace $record): ?string => $record->currentSubscription()?->stripe_status)
                    ->placeholder('None'),
                TextColumn::make('members_count')
                    ->label('Members'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('activity')
                    ->label('Activity')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->color('gray')
                    ->url(fn (Workspace $record): string => AuditEventResource::getUrl('index', ['filters' => ['workspace' => ['value' => $record->getKey()]]])),
                Action::make('changePlan')
                    ->label('Change plan')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->fillForm(fn (Workspace $record): array => ['plan' => $record->plan])
                    ->schema([
                        Select::make('plan')
                            ->options(fn (): array => array_map(fn (Plan $plan): string => $plan->label, Features::plans()))
                            ->required()
                            ->helperText('A Stripe subscription sets the plan again whenever it changes.'),
                    ])
                    ->action(fn (Workspace $record, array $data) => $record->changePlan($data['plan']))
                    ->successNotificationTitle('Plan changed'),
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
