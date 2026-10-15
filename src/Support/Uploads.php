<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Entry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
     * L'identifiant d'un fichier, le même dans les deux applications : celui de l'application où il a été joint
     * (`remote_uuid` quand il a été reçu).
     */
    public static function uuidOf(Media $media): string
    {
        return (string) $media->getCustomProperty('remote_uuid', $media->uuid);
    }

    /**
     * Retire des fichiers joints, par identifiant (`uuidOf()`).
     *
     * @param  array<int, string>  $uuids
     */
    public static function remove(HasMedia $model, array $uuids): void
    {
        if ($uuids === []) {
            return;
        }

        $model->getMedia(Entry::ATTACHMENTS_COLLECTION)
            ->filter(fn (Media $media): bool => in_array(self::uuidOf($media), $uuids, true))
            ->each(fn (Media $media) => $media->delete());
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

    /**
     * Les fichiers envoyés, en base64 (format de `Payload::files()`), pour les transmettre à l'autre application.
     *
     * @param  array<int, array{path: string, name?: string}>  $uploads
     * @return array<int, array<string, mixed>>
     */
    public static function toBase64(array $uploads): array
    {
        $disk = Storage::disk(config('filament-tma.attachments.disk', 'local'));

        return collect($uploads)->map(function (array $upload) use ($disk): array {
            $name = $upload['name'] ?? basename($upload['path']);

            return [
                'uuid' => (string) Str::uuid(),
                'name' => pathinfo($name, PATHINFO_FILENAME),
                'file_name' => self::safeFileName($name),
                'mime_type' => $disk->mimeType($upload['path']),
                'size' => $disk->size($upload['path']),
                'content' => base64_encode((string) $disk->get($upload['path'])),
            ];
        })->values()->all();
    }

    /** @param  array<int, array{path: string, name?: string}>  $uploads */
    public static function discard(array $uploads): void
    {
        $disk = Storage::disk(config('filament-tma.attachments.disk', 'local'));

        foreach ($uploads as $upload) {
            $disk->delete($upload['path']);
        }
    }

    public static function safeFileName(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'fichier';

        return $extension !== '' ? "{$base}.{$extension}" : $base;
    }
}
