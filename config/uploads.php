<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Uploaded files
    |--------------------------------------------------------------------------
    |
    | Workspace logos and profile pictures. `disk` is where they are stored:
    | `public` (storage/app/public, linked into public/ by `php artisan
    | storage:link`) by default, `s3` for an S3 or S3-compatible bucket.
    | Anyone with a file's URL can open it, so keep these files to what may
    | be public. On Laravel Cloud, attach a public bucket with the disk name
    | `public` (docs/deploy/laravel-cloud.md).
    |
    | Every file sits under its owner's folder (App\Support\Uploads), which
    | is removed when the workspace or the account is deleted.
    |
    | `visibility` is passed to the disk with each file; null lets Filament
    | choose (public on the `public` disk, private elsewhere). A bucket that
    | decides visibility for itself, like Cloudflare R2 behind Laravel Cloud,
    | may refuse a per-file setting.
    |
    */

    'disk' => env('UPLOADS_DISK', 'public'),

    'visibility' => env('UPLOADS_VISIBILITY'),

    // In kilobytes.
    'max_size' => (int) env('UPLOADS_MAX_SIZE', 1024),

    // No SVG: a picture people upload is served from your domain, and an SVG can carry scripts.
    'image_types' => ['image/png', 'image/jpeg', 'image/webp'],

];
