<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Upload Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used for file uploads. Set to "r2" for Cloudflare R2
    | in production, or "public" for local development.
    |
    */

    'disk' => env('UPLOAD_DISK', 'r2'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload File Size (KB)
    |--------------------------------------------------------------------------
    |
    | The maximum allowed file size for uploads in kilobytes.
    | Default: 5120 KB = 5 MB.
    |
    */

    'max_file_size' => (int) env('UPLOAD_MAX_FILE_SIZE', 5120),

    /*
    |--------------------------------------------------------------------------
    | CDN URL
    |--------------------------------------------------------------------------
    |
    | The public CDN base URL for serving uploaded files.
    | Used by the CdnUrl helper to generate public-facing URLs.
    |
    */

    'cdn_url' => env('CLOUDFLARE_R2_CDN_URL'),

];
