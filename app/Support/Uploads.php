<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Where uploaded files (receipts, signed contracts) are kept.
 *
 * Locally they stay on this server's disks ("public" or "local"). In production set
 * UPLOADS_DISK=spaces and every upload goes to DigitalOcean Spaces instead, so files
 * survive redeploys. Links to Spaces files are short-lived signed URLs (files stay private).
 */
class Uploads
{
    /** The disk uploads go to: UPLOADS_DISK when set, otherwise the disk the screen always used. */
    public static function disk(string $fallback): string
    {
        return config('filesystems.uploads') ?: $fallback;
    }

    public static function store(UploadedFile $file, string $folder, string $fallback = 'public'): string
    {
        return $file->store($folder, self::disk($fallback));
    }

    /** A link to open the file in the browser. */
    public static function url(?string $path, string $fallback = 'public'): ?string
    {
        if (! $path) return null;
        $disk = self::disk($fallback);

        return config("filesystems.disks.{$disk}.driver") === 's3'
            ? Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15))
            : Storage::disk($disk)->url($path);
    }

    public static function exists(?string $path, string $fallback = 'public'): bool
    {
        return $path && Storage::disk(self::disk($fallback))->exists($path);
    }

    /** Show the file inline (e.g. a deposit receipt). */
    public static function response(string $path, string $fallback = 'public'): StreamedResponse
    {
        return Storage::disk(self::disk($fallback))->response($path, basename($path),
            ['Content-Disposition' => 'inline; filename="'.basename($path).'"']);
    }
}
