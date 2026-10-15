<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\StateTransition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * La chronologie affichée : les messages (avec leurs réponses et leurs notes) et les changements d'état, par date.
 * Les notes sur le ticket entier sont à part (`Trackable::noteEntries()`).
 */
final class TimelineItems
{
    /** @return Collection<int, array{kind: string, at: Carbon, item: Entry|StateTransition}> */
    public static function for(Trackable $ticket): Collection
    {
        $entries = $ticket->timelineEntries()
            ->map(fn (Entry $entry): array => ['kind' => 'entry', 'at' => $entry->created_at, 'item' => $entry]);

        $transitions = $ticket->transitions()->with('actor')->get()
            ->map(fn (StateTransition $transition): array => ['kind' => 'transition', 'at' => $transition->created_at, 'item' => $transition]);

        // Par date, puis, à la même seconde, dans l'ordre où tout a été fait (les ulid suivent l'heure de création).
        return $entries->concat($transitions)
            ->sortBy(fn (array $row): string => $row['at']->format('Y-m-d H:i:s').' '.strtolower((string) $row['item']->ulid))
            ->values();
    }

    /**
     * Les passages qui ont écrit un message, par message : la question de « Demander des précisions », la réponse, le
     * texte de « Résolu »… Le message cite son passage (`meta.transition`) ; un message plus ancien, écrit par le
     * passage lui-même (ses marques `question`, `answer`, `reopen`), le retrouve chez le même auteur, à la même seconde
     * ou presque. Un message écrit à part, même juste après un passage, n'en a pas.
     *
     * @param  Collection<int, Entry>  $entries  les messages de premier niveau (leurs réponses suivent)
     * @param  Collection<int, StateTransition>  $transitions
     * @return Collection<int, StateTransition> par identifiant de message
     */
    public static function movesOfMessages(Collection $entries, Collection $transitions): Collection
    {
        $byUlid = $transitions->filter(fn (StateTransition $t): bool => filled($t->ulid))->keyBy('ulid');
        $legacy = ['question' => 'request-answer', 'answer' => 'answer', 'reopen' => 'reopen'];
        $used = [];

        return $entries->flatMap(fn (Entry $entry) => [$entry, ...$entry->replies])
            ->mapWithKeys(function (Entry $entry) use ($byUlid, $transitions, $legacy, &$used): array {
                $meta = (array) $entry->meta;
                $transition = $byUlid->get($meta['transition'] ?? '');

                if ($transition === null && ! array_key_exists('transition', $meta)) {
                    $move = collect($legacy)->first(fn (string $move, string $flag): bool => (bool) ($meta[$flag] ?? false)) ?? 'submit-for-validation';
                    $transition = $transitions->first(fn (StateTransition $t): bool => $t->move === $move
                        && ! in_array($t->getKey(), $used, true)
                        && (int) $t->actor_id === (int) $entry->author_actor_id
                        && abs($t->created_at->diffInSeconds($entry->created_at, false)) <= 5);
                }

                if ($transition === null) {
                    return [];
                }

                $used[] = $transition->getKey();

                return [$entry->getKey() => $transition];
            });
    }
}
