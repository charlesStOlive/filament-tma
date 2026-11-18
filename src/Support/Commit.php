<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Le commit qui porte un message du Support sur un ticket (`meta.commit`), facultatif : son empreinte (« 704f0e3 », de
 * 7 à 40 caractères) ou son lien (`https://github.com/…/commit/…`). Un lien s'ouvre depuis l'historique ; une
 * empreinte seule s'affiche telle quelle.
 *
 * Seul un message du Support en porte un : celui d'un passage (« En cours », « Demander des précisions », « Marquer
 * comme résolu »), qu'on peut modifier tant qu'il est le dernier.
 */
final class Commit
{
    public const MAX_LENGTH = 255;

    private const HASH = '/^[0-9a-f]{7,40}$/i';

    /** L'empreinte dans un lien : GitHub (`/commit/…`), GitLab (`/-/commit/…`), Bitbucket (`/commits/…`). */
    private const HASH_IN_URL = '~/commits?/([0-9a-f]{7,40})(?:[/?#]|$)~i';

    /** @return array<int, string> les règles de validation du champ (vide accepté) */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_LENGTH, 'regex:/^\s*([0-9a-f]{7,40}|https?:\/\/\S+)\s*$/i'];
    }

    /** Le champ, dans la zone de réponse, les passages du Support et la modification d'un message. */
    public static function field(string $name = 'commit'): TextInput
    {
        return TextInput::make($name)
            ->label(__('filament-tma::messages.commit.label'))
            ->placeholder(__('filament-tma::messages.commit.placeholder'))
            ->prefixIcon('heroicon-m-code-bracket')
            ->maxLength(self::MAX_LENGTH)
            ->rules(self::rules())
            ->hintAction(Help::action('tma.chronologie.commits', __('filament-tma::messages.commit.help')));
    }

    /** Ce qu'on garde : sans espaces autour, une empreinte en minuscules ; null pour rien. */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return preg_match(self::HASH, $value) === 1 ? strtolower($value) : $value;
    }

    /** L'empreinte, dite ou lue dans le lien ; null pour un lien qui n'en montre pas. */
    public static function hash(string $commit): ?string
    {
        if (preg_match(self::HASH, $commit) === 1) {
            return strtolower($commit);
        }

        return preg_match(self::HASH_IN_URL, $commit, $matches) === 1 ? strtolower($matches[1]) : null;
    }

    /** Ce qu'on affiche : l'empreinte courte (7 caractères), sinon le lien sans son protocole. */
    public static function label(string $commit): string
    {
        $hash = self::hash($commit);

        return $hash !== null ? substr($hash, 0, 7) : Str::limit((string) preg_replace('~^https?://~i', '', $commit), 40);
    }

    /** Le lien à ouvrir, quand on en a un. */
    public static function url(string $commit): ?string
    {
        return preg_match('~^https?://~i', $commit) === 1 ? $commit : null;
    }

    /** Le commit d'un message, s'il en a un. */
    public static function of(Entry $entry): ?string
    {
        $commit = $entry->meta['commit'] ?? null;

        return is_string($commit) && $commit !== '' ? $commit : null;
    }

    /** Un message de ce ticket écrit par le Support (l'autre côté que le demandeur) : il peut porter un commit. */
    public static function allowedOn(Entry $entry): bool
    {
        $subject = $entry->subject;

        return $subject instanceof Ticket && ! $entry->isNote() && $entry->author !== null
            && $entry->author->instance_uid !== $subject->source_instance_uid;
    }

    /**
     * Les commits d'un ticket, du plus ancien au plus récent, chacun une fois.
     *
     * @return Collection<int, array{commit: string, label: string, url: ?string, at: \DateTimeInterface}>
     */
    public static function forTicket(Ticket $ticket): Collection
    {
        return $ticket->entries()->messages()->whereNotNull('meta->commit')->oldest('id')->get()
            ->map(fn (Entry $entry): ?array => ($commit = self::of($entry)) === null ? null : [
                'commit' => $commit,
                'label' => self::label($commit),
                'url' => self::url($commit),
                'at' => $entry->created_at,
            ])
            ->filter()
            ->unique('commit')
            ->values();
    }
}
