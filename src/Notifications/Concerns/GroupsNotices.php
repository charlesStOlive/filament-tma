<?php

namespace CharlesStOlive\FilamentTma\Notifications\Concerns;

use CharlesStOlive\FilamentTma\Models\Notice;
use Illuminate\Support\Collection;

/** Les notifications mises de côté, rangées pour un bilan : par ticket ou tâche, dans l'ordre où elles sont arrivées. */
trait GroupsNotices
{
    /**
     * @param  Collection<int, Notice>  $notices
     * @return array<int, array{ref: string, title: string, url: ?string, subject: mixed, lines: array<int, array{line: string, excerpt: ?string}>}>
     */
    protected static function groups(Collection $notices): array
    {
        return $notices->sortBy('id')
            ->groupBy(fn (Notice $notice): string => $notice->subject_type.':'.$notice->subject_id)
            ->map(fn (Collection $group): array => [
                'ref' => (string) $group->first()->data['ref'],
                // Le titre d'aujourd'hui, s'il a changé depuis.
                'title' => (string) ($group->first()->subject?->title ?? $group->first()->data['title']),
                'url' => $group->first()->url(),
                'subject' => $group->first()->subject,
                'project' => $group->first()->data['project'] ?? null,
                'lines' => self::lines($group),
            ])
            ->values()->all();
    }

    /**
     * @param  Collection<int, Notice>  $notices
     * @return array<int, array{line: string, excerpt: ?string}>
     */
    protected static function lines(Collection $notices): array
    {
        return $notices->sortBy('id')
            ->map(fn (Notice $notice): array => ['line' => (string) $notice->data['line'], 'excerpt' => $notice->data['excerpt'] ?? $notice->data['message'] ?? null])
            ->values()->all();
    }

    /**
     * Les notifications d'un destinataire principal, puis celles où il n'est qu'en copie.
     *
     * @param  Collection<int, Notice>  $notices
     * @return array{0: Collection<int, Notice>, 1: Collection<int, Notice>}
     */
    protected static function splitCopies(Collection $notices): array
    {
        [$copies, $own] = $notices->partition(fn (Notice $notice): bool => ($notice->data['role'] ?? 'primary') === 'secondary');

        return [$own->values(), $copies->values()];
    }
}
