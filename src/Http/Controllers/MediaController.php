<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le téléchargement d'un fichier d'un ticket ou d'une entrée, avec contrôle à chaque fois (CDC §17.3) : on doit
 * pouvoir voir le ticket ; l'entrée ne doit pas avoir été supprimée.
 * Les fichiers sont sur un disque privé : il n'y a pas d'autre chemin vers eux.
 */
class MediaController
{
    public function __invoke(Request $request, Media $media): Response
    {
        $owner = $media->model;

        $ticket = match (true) {
            $owner instanceof Trackable => $owner,
            $owner instanceof Entry && $owner->subject instanceof Trackable => $owner->subject,
            default => abort(404),
        };

        $user = $request->user();
        abort_if($user === null, 401);

        $policy = Gate::getPolicyFor($ticket::class);
        abort_if($policy !== null && ! Gate::forUser($user)->allows('view', $ticket), 403);

        abort_if($owner instanceof Entry && $owner->trashed(), 404);

        $inline = $request->boolean('inline') && str_starts_with((string) $media->mime_type, 'image/');

        return response()->streamDownload(function () use ($media): void {
            $stream = $media->stream();
            fpassthru($stream);
            fclose($stream);
        }, $media->file_name, [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }
}
