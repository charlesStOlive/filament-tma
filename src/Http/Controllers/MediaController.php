<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le téléchargement d'un fichier d'un ticket ou d'une entrée, avec contrôle à chaque fois (CDC §17.3) : on doit
 * pouvoir voir le ticket, et l'entrée (une note interne n'est lisible qu'avec le droit des notes).
 * Les fichiers sont sur un disque privé : il n'y a pas d'autre chemin vers eux.
 */
class MediaController
{
    public function __invoke(Request $request, Media $media): Response
    {
        $owner = $media->model;

        $ticket = match (true) {
            $owner instanceof Ticket => $owner,
            $owner instanceof Entry && $owner->subject instanceof Ticket => $owner->subject,
            default => abort(404),
        };

        $user = $request->user();
        abort_if($user === null, 401);

        $policy = Gate::getPolicyFor(Ticket::class);
        abort_if($policy !== null && ! Gate::forUser($user)->allows('view', $ticket), 403);

        if ($owner instanceof Entry) {
            $withNotes = app(Timeline::class)->canReadNotes($ticket, Context::ui($user));
            $visible = Entry::query()->whereKey($owner->getKey())->visibleHere($withNotes)->exists();
            abort_unless($visible, 403);
        }

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
