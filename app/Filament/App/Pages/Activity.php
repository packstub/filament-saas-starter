<?php

namespace App\Filament\App\Pages;

use App\Enums\AuditEventType;
use App\Models\AuditEvent;
use App\Models\Workspace;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The workspace's audit log, for owners and admins: who joined, left,
 * changed roles, was invited, took over ownership, and every plan change.
 * The entries come from App\Models\AuditEvent; they read only what the
 * entry itself holds, so the page never queries another table.
 */
class Activity extends Page implements HasTable
{
    public static function getNavigationGroup(): ?string
    {
        return __('app.navigation.workspace');
    }

    use InteractsWithTable;

    public static function getNavigationLabel(): string
    {
        return __('activity.title');
    }

    public function getTitle(): string
    {
        return __('activity.title');
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'activity';

    protected string $view = 'filament.app.pages.activity';

    public static function canAccess(): bool
    {
        $workspace = Filament::getTenant();

        return $workspace instanceof Workspace && (bool) Filament::auth()->user()?->canManageTeam($workspace);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => AuditEvent::query()->where('workspace_id', Filament::getTenant()->getKey()))
            ->columns([
                TextColumn::make('event')
                    ->label(__('activity.columns.event'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('activity.columns.description'))
                    ->state(fn (AuditEvent $record): string => $record->description())
                    ->wrap(),
                TextColumn::make('actor_name')
                    ->label(__('activity.columns.by'))
                    ->state(fn (AuditEvent $record): string => $record->actorLabel())
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('activity.columns.when'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('activity.columns.event'))
                    ->options(AuditEventType::class),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('activity.empty'));
    }
}
