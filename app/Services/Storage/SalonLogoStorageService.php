<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SalonLogoStorageService
{
    private const DISK = 'public';

    private const FOLDER = 'logos';

    /** Extension déduite du type MIME détecté côté serveur, jamais du nom envoyé par le client (audit H1). */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function upload(UploadedFile $file): string
    {
        $extension = self::EXTENSIONS[$file->getMimeType()] ?? null;

        if ($extension === null) {
            throw new \RuntimeException('Format de logo non supporté.');
        }

        $filename = Str::uuid().'.'.$extension;
        $path = self::FOLDER.'/'.$filename;

        Storage::disk(self::DISK)->put($path, file_get_contents($file));

        return $path;
    }

    public function delete(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return Storage::disk(self::DISK)->delete($path);
    }

    public function url(?string $path): ?string
    {
        if (! $path || ! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
