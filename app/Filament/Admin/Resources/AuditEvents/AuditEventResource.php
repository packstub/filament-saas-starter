<?php

namespace App\Filament\Admin\Resources\AuditEvents;

use App\Enums\AuditEventType;
use App\Filament\Admin\Resources\AuditEvents\Pages\ListAuditEvents;
use App\Models\AuditEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every workspace's audit log in one place, deleted workspaces included.
 * Read-only: entries are written by AuditEvent::record() and never edited.
 * Like the rest of the admin panel it reads central tables only; each row
 * carries the names it shows.
 */
class AuditEventResource extends Resource
{
    public static function getNavigationGroup(): ?string
    {
        return __('ui_app.navigation.customers');
    }

    protected static ?string $model = AuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getModelLabel(): string
    {
        return __('ui_admin.activity.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui_admin.activity.plural_model');
    }

    public static function getTitleCaseModelLabel(): string
    {
        return __('ui_admin.activity.title');
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return __('ui_admin.activity.plural_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui_admin.activity.navigation');
    }

    protected static ?string $slug = 'activity';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace_name')
                    ->label(__('ui_admin.activity.columns.workspace'))
                    ->searchable(),
                TextColumn::make('event')
                    ->label(__('ui_admin.activity.columns.event'))
                    ->badge(),
                TextColumn::make('description')
                    ->label(__('ui_admin.activity.columns.description'))
                    ->state(fn (AuditEvent $record): string => $record->description())
                    ->wrap(),
                TextColumn::make('actor_name')
                    ->label(__('ui_admin.activity.columns.by'))
                    ->state(fn (AuditEvent $record): string => $record->actorLabel())
                    ->searchable(['actor_name', 'impersonator_name']),
                TextColumn::make('created_at')
                    ->label(__('ui_admin.activity.columns.when'))
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(__('ui_admin.activity.filters.event'))
                    ->options(AuditEventType::class),
                // Also set by "Activity" on a row of the Workspaces table. A deleted
                // workspace is not in the list; search for its name instead.
                SelectFilter::make('workspace')
                    ->label(__('ui_admin.activity.filters.workspace'))
                    ->relationship('workspace', 'name')
                    ->searchable(),
                Filter::make('support_logins')
                    ->label(__('ui_admin.activity.filters.support_logins'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('impersonator_id'))
                    ->toggle(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditEvents::route('/'),
        ];
    }
}
