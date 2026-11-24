<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A workspace's API token (Laravel Sanctum's personal access token, with
 * the workspace as its owner). It keeps who created it, by id and by name;
 * their tokens are revoked once they no longer manage the workspace
 * (App\Listeners\RevokeApiTokensOfFormerManagers).
 *
 * Tokens are central, like billing: a request names no workspace, the
 * token does, so the table has to be readable before any workspace is
 * known. Sanctum stores only a hash; the token itself is shown once.
 */
#[Fillable(['name', 'token', 'abilities', 'expires_at', 'created_by_id', 'created_by_name'])]
class ApiToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    /**
     * What a token may be allowed to do, with the label the API tokens page shows.
     * Each one is checked on its routes in routes/api.php (`abilities:…`).
     */
    public const ABILITIES = ['projects:read', 'projects:write'];

    /**
     * The abilities as ability => label, in the current language (lang/*\/api_tokens.php).
     *
     * @return array<string, string>
     */
    public static function abilityOptions(): array
    {
        return array_combine(self::ABILITIES, array_map(self::abilityLabel(...), self::ABILITIES));
    }

    /**
     * The label of one ability; the ability itself when it is not a known one.
     */
    public static function abilityLabel(string $ability): string
    {
        return in_array($ability, self::ABILITIES, true) ? __("ui_api_tokens.abilities.{$ability}") : $ability;
    }
}
