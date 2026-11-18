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
    | `visibility` is set on each file after it is stored. These files are
    | public by design (their plain URL is used in the menus and e-mails), so
    | it is `public` on every disk; a bucket that refuses per-file settings
    | (S3 with ACLs disabled, Cloudflare R2) keeps the file and its own
    | setting, so make that bucket publicly readable.
    |
    */

    'disk' => env('UPLOADS_DISK', 'public'),

    'visibility' => env('UPLOADS_VISIBILITY', 'public'),

    // In kilobytes.
    'max_size' => (int) env('UPLOADS_MAX_SIZE', 1024),

    // No SVG: a picture people upload is served from your domain, and an SVG can carry scripts.
    'image_types' => ['image/png', 'image/jpeg', 'image/webp'],

];
