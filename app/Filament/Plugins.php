<?php

namespace App\Filament;

use Filament\Contracts\Plugin;

/**
 * The optional plugins each panel registers on top of the kit's own: the
 * one place a module (docs/modules) is switched on. The panel providers
 * keep the kit's plugins and their settings.
 */
final class Plugins
{
    /**
     * The customer panel, at /app/{workspace}.
     *
     * @return list<Plugin>
     */
    public static function app(): array
    {
        return [
            // A module's recipe says which line to add here.
        ];
    }

    /**
     * The back office, at /admin.
     *
     * @return list<Plugin>
     */
    public static function admin(): array
    {
        return [
            // A module's recipe says which line to add here.
        ];
    }
}
