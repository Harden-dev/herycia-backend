<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MinioStorageService
{
    private string $defaultDisk = 'minio';

    /**
     * Upload un fichier sur MinIO avec un nom sécurisé
     */
    public function upload(UploadedFile $file, string $folder, ?string $disk = null): string
    {
        $disk = $disk ?? $this->defaultDisk;
        // Extension déduite du contenu (MIME détecté), jamais du nom fourni par le client (audit H1).
        $extension = $file->guessExtension() ?? 'bin';
        $filename = Str::uuid() . '.' . $extension;
        $path = $folder . '/' . $filename;

        Storage::disk($disk)->put($path, file_get_contents($file));

        return $path;
    }

    /**
     * Supprime un fichier de MinIO
     */
    public function delete(?string $path, ?string $disk = null): bool
    {
        if (!$path) {
            return false;
        }

        $disk = $disk ?? $this->defaultDisk;
        return Storage::disk($disk)->delete($path);
    }

    /**
     * Génère une URL présignée (temporaire et sécurisée)
     *
     * @param string|null $path Chemin du fichier
     * @param int $expiresInMinutes Durée de validité en minutes (défaut: 60)
     * @param string|null $disk Disk à utiliser
     */
    public function getTemporaryUrl(?string $path, int $expiresInMinutes = 60, ?string $disk = null): ?string
    {
        $disk = $disk ?? $this->defaultDisk;

        if (!$path || !Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->temporaryUrl(
            $path,
            now()->addMinutes($expiresInMinutes)
        );
    }

    /**
     * Vérifie si un fichier existe
     */
    public function exists(?string $path, ?string $disk = null): bool
    {
        if (!$path) {
            return false;
        }

        $disk = $disk ?? $this->defaultDisk;
        return Storage::disk($disk)->exists($path);
    }

    /**
     * Récupère le contenu d'un fichier
     */
    public function get(string $path, ?string $disk = null): ?string
    {
        $disk = $disk ?? $this->defaultDisk;

        if (!Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->get($path);
    }
}

