<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Range des fichiers envoyés (par un formulaire Filament, sur le disque des pièces jointes) dans la collection
 * `attachments` d'un ticket ou d'une entrée, une fois contrôlés (`Attachments::problem()` : type réel, extension,
 * macros). medialibrary les déplace.
 *
 * medialibrary ne tient pas à jour la liste des fichiers déjà chargée sur le modèle quand on en ajoute ou en retire :
 * après chaque changement, `fresh()` la relit, sinon ce qui part ensuite vers l'autre application (`Payload`) ou
 * ce qu'on y compare (`Mirror`) serait l'ancienne liste.
 */
final class Uploads
{
    /** @param  array<int, array{path: string, name?: string}>  $uploads */
    public static function attach(HasMedia $model, array $uploads): void
    {
        $disk = config('filament-tma.attachments.disk', 'local');

        foreach ($uploads as $upload) {
            $original = $upload['name'] ?? basename($upload['path']);
            Attachments::assertAcceptable(Attachments::problemOnDisk($upload['path'], $disk, $original));

            $model->addMediaFromDisk($upload['path'], $disk)
                ->usingName(pathinfo($original, PATHINFO_FILENAME))
                ->usingFileName(self::safeFileName($original))
                ->toMediaCollection(Entry::ATTACHMENTS_COLLECTION);
        }

        self::fresh($model);
    }

    /** Relit la liste des fichiers du modèle, après un ajout ou un retrait. */
    public static function fresh(HasMedia $model): void
    {
        if ($model instanceof Model && $model->exists) {
            $model->load('media');
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
     * Retire des fichiers joints, par identifiant (`uuidOf()`). Dans une transaction, le fichier ne quitte le disque
     * qu'une fois elle validée : annulée (le maître n'a pas répondu…), la pièce jointe reste entière.
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
            ->each(function (Media $media): void {
                $media::withoutEvents(fn () => $media->delete());
                DB::afterCommit(fn () => app(Filesystem::class)->removeAllFiles($media));
            });

        self::fresh($model);
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
