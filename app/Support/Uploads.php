<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where uploaded files go (config/uploads.php): each under the folder of its
 * owner, the workspace or the person, so deleting the owner removes its
 * folder and a path can be checked against the record it is saved on.
 */
final class Uploads
{
    public static function diskName(): string
    {
        return config('uploads.disk');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function workspaceDirectory(Workspace $workspace): string
    {
        return 'workspaces/'.$workspace->getKey();
    }

    public static function userDirectory(User $user): string
    {
        return 'users/'.$user->getKey();
    }

    /**
     * Whether a stored path lies inside the given folder: a form can only
     * point a record at its own files, never at someone else's.
     */
    public static function isInside(string $path, string $directory): bool
    {
        return ! Str::contains($path, '..') && Str::startsWith($path, $directory.'/');
    }

    public static function url(?string $path): ?string
    {
        return filled($path) ? self::disk()->url($path) : null;
    }

    /**
     * Remove a file, unless it lies outside its owner's folder.
     */
    public static function delete(?string $path, string $directory): void
    {
        if (filled($path) && self::isInside($path, $directory)) {
            self::disk()->delete($path);
        }
    }
}
