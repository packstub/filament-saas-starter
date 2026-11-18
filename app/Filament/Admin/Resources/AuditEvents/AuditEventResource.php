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
use UnitEnum;

/**
 * Every workspace's audit log in one place, deleted workspaces included.
 * Read-only: entries are written by AuditEvent::record() and never edited.
 * Like the rest of the admin panel it reads central tables only; each row
 * carries the names it shows.
 */
class AuditEventResource extends Resource
{
    protected static ?string $model = AuditEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Activity';

    protected static ?string $modelLabel = 'activity entry';

    protected static ?string $slug = 'activity';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace_name')
                    ->label('Workspace')
                    ->searchable(),
                TextColumn::make('event')
                    ->badge(),
                TextColumn::make('description')
                    ->state(fn (AuditEvent $record): string => $record->description())
                    ->wrap(),
                TextColumn::make('actor_name')
                    ->label('By')
                    ->state(fn (AuditEvent $record): string => $record->actorLabel())
                    ->searchable(['actor_name', 'impersonator_name']),
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options(AuditEventType::class),
                // Also set by "Activity" on a row of the Workspaces table. A deleted
                // workspace is not in the list; search for its name instead.
                SelectFilter::make('workspace')
                    ->relationship('workspace', 'name')
                    ->searchable(),
                Filter::make('support_logins')
                    ->label('During a support login')
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
