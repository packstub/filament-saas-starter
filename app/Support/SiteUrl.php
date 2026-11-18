<?php

namespace App\Support;

/**
 * Absolute URLs of the public site, always on APP_URL. What search engines
 * and link previews keep (the canonical URL, og:url, the sitemap) must not
 * follow the Host header of whichever request rendered the page: a proxy,
 * a platform hostname or a forged header would end up indexed.
 */
final class SiteUrl
{
    public static function to(string $path = '/'): string
    {
        $path = trim(parse_url($path, PHP_URL_PATH) ?? '', '/');

        return rtrim((string) config('app.url'), '/').($path === '' ? '' : '/'.$path);
    }

    /**
     * The public page a named route serves.
     */
    public static function route(string $name): string
    {
        return self::to(route($name, absolute: false));
    }
}
