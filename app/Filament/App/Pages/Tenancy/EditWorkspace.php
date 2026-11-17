<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Models\Workspace;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

/**
 * "Workspace settings" in the workspace menu, for owners and admins
 * (WorkspacePolicy::update()). The slug stays as it is so links keep working.
 */
class EditWorkspace extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Workspace settings';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(Workspace::NAME_MAX_LENGTH),
        ]);
    }
}
