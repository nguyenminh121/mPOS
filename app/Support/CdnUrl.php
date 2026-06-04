<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class CdnUrl
{
    /**
     * Generate a public CDN URL for an uploaded file path.
     *
     * Falls back to the disk's own URL generation when no CDN URL is configured
     * (e.g., local development using the "public" disk).
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $cdnBase = config('upload.cdn_url');

        if ($cdnBase) {
            return rtrim($cdnBase, '/') . '/' . ltrim($path, '/');
        }

        // Fallback: use the configured upload disk to generate the URL
        return Storage::disk(config('upload.disk'))->url($path);
    }
}
