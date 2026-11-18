<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What the `status` column of a project holds. Filament reads the label and
 * the colour from here, for the select in the form and the badge in the table.
 */
enum ProjectStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Paused = 'paused';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('projects.statuses.active'),
            self::Paused => __('projects.statuses.paused'),
            self::Done => __('projects.statuses.done'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Done => 'gray',
        };
    }
}
