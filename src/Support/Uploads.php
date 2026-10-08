<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Entry;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * Range des fichiers envoyés (par un formulaire Filament, sur le disque des pièces jointes) dans la collection
 * `attachments` d'un ticket ou d'une entrée. medialibrary les déplace et contrôle leur type réel.
 */
final class Uploads
{
    /** @param  array<int, array{path: string, name?: string}>  $uploads */
    public static function attach(HasMedia $model, array $uploads): void
    {
        $disk = config('filament-tma.attachments.disk', 'local');

        foreach ($uploads as $upload) {
            $original = $upload['name'] ?? basename($upload['path']);

            $model->addMediaFromDisk($upload['path'], $disk)
                ->usingName(pathinfo($original, PATHINFO_FILENAME))
                ->usingFileName(self::safeFileName($original))
                ->toMediaCollection(Entry::ATTACHMENTS_COLLECTION);
        }
    }

    /**
     * Les fichiers d'un formulaire : `attachments` (chemins) et `attachment_names` (chemin => nom d'origine).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array{path: string, name: string}>
     */
    public static function fromForm(array $data): array
    {
        $names = $data['attachment_names'] ?? [];

        return collect((array) ($data['attachments'] ?? []))
            ->map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)])
            ->values()->all();
    }

    public static function safeFileName(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'fichier';

        return $extension !== '' ? "{$base}.{$extension}" : $base;
    }
}
