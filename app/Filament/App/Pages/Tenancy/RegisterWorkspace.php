<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Models\Workspace;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Packstub\Teams\Facades\Teams;

/**
 * "New workspace" in the workspace menu. Signing up already creates a first
 * workspace (see AppPanelProvider), so this is for a second one, and for
 * someone whose last workspace is gone.
 */
class RegisterWorkspace extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('ui_workspace.new');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(Workspace::NAME_MAX_LENGTH)
                ->autofocus(),
        ]);
    }

    /**
     * @param  array{name: string}  $data
     */
    protected function handleRegistration(array $data): Workspace
    {
        $workspace = Workspace::named($data['name']);

        Teams::addMember($workspace, Filament::auth()->user(), Teams::ownerRole());

        return $workspace;
    }
}
