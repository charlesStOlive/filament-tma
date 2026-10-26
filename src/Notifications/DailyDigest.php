<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Notifications\Concerns\GroupsNotices;
use CharlesStOlive\FilamentTma\Support\Dates;
use CharlesStOlive\FilamentTma\Support\MailBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Le bilan du matin d'un utilisateur (`resources/views/mail/digest.blade.php`) :
 *
 * - **À faire** : ce qui l'attend (`Support\Digest`), avec, sous chaque ticket, ce qui s'y est passé depuis le dernier
 *   envoi ; les questions du Support sur ses tâches, avec leur texte ;
 * - **Nouveautés** : ce qui a été mis de côté pour lui ailleurs (une fin de ticket, ce qu'on attend de lui sans
 *   e-mail direct…) ;
 * - **En copie**, en bas : là où il n'est que destinataire secondaire.
 */
class DailyDigest extends Notification implements ShouldQueue
{
    use GroupsNotices;
    use Queueable;

    /**
     * @param  array<string, Collection<int, array<string, mixed>>>  $sections
     * @param  Collection<int, Notice>|null  $pending
     */
    public function __construct(public array $sections, public ?Collection $pending = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $notifiable instanceof Authenticatable ? $notifiable : null;
        [$own, $copies] = self::splitCopies($this->pending ?? collect());
        $refs = collect($this->sections)->flatten(1)->pluck('ref')->all();
        [$attached, $news] = $own->partition(fn (Notice $notice): bool => in_array($notice->data['ref'] ?? null, $refs, true));
        $titles = [
            'answer' => __('filament-tma::notifications.daily.sections.yours'),
            'take' => __('filament-tma::notifications.daily.sections.unassigned'),
            'mine' => __('filament-tma::notifications.daily.sections.handling'),
            'resume' => __('filament-tma::notifications.daily.sections.resuming'),
            'questions' => __('filament-tma::notifications.daily.sections.questions'),
        ];

        $todo = collect($this->sections)->map(fn (Collection $items, string $key): array => [
            'title' => $titles[$key] ?? $key,
            'items' => $items->map(fn (array $item): array => [
                ...$item,
                'since' => isset($item['since']) ? Dates::format($item['since'], 'd/m/Y', $user) : null,
                'closes_on' => isset($item['closes_on']) ? Dates::format($item['closes_on'], 'd/m/Y', $user) : null,
                'lines' => $item['lines'] ?? self::lines($attached->filter(fn (Notice $notice): bool => ($notice->data['ref'] ?? null) === $item['ref'])),
            ])->values()->all(),
        ])->values()->all();

        $count = collect($this->sections)->sum(fn (Collection $items): int => $items->count());
        $questions = count($this->sections['questions'] ?? []);
        $tickets = $count - $questions;
        $late = collect($this->sections)->sum(fn (Collection $items): int => $items->where('late', true)->count());
        $newsGroups = self::groups($news);
        $copyGroups = self::groups($copies);

        $summary = collect([
            $count > 0 ? trans_choice('filament-tma::notifications.daily.summary.todo', $count, ['n' => $count]).($late > 0 ? __('filament-tma::notifications.daily.summary.overdue', ['n' => $late]) : '') : null,
            $newsGroups !== [] ? trans_choice('filament-tma::notifications.daily.summary.news', count($newsGroups), ['n' => count($newsGroups)]) : null,
            $copyGroups !== [] ? trans_choice('filament-tma::notifications.daily.summary.copy', count($copyGroups), ['n' => count($copyGroups)]) : null,
        ])->filter()->implode(', ');

        return (new MailMessage)
            ->subject(__('filament-tma::notifications.daily.subject', ['summary' => $summary]))
            ->view(['filament-tma::mail.digest', 'filament-tma::mail.digest-text'], [
                'brand' => MailBrand::for('ticket'),
                'kicker' => __('filament-tma::notifications.daily.kicker', ['date' => Dates::format(now(), 'd/m/Y', $user)]),
                'heading' => __('filament-tma::notifications.daily.title'),
                'preheader' => ucfirst($summary).'.',
                'todo' => $todo,
                'todoText' => match (true) {
                    $questions === 0 => trans_choice('filament-tma::notifications.daily.intro.tickets', $tickets, ['n' => $tickets]),
                    $tickets === 0 => trans_choice('filament-tma::notifications.daily.intro.questions', $questions, ['n' => $questions]),
                    default => __('filament-tma::notifications.daily.intro.both', [
                        'tickets' => trans_choice('filament-tma::notifications.daily.intro.ticket_count', $tickets, ['n' => $tickets]),
                        'questions' => trans_choice('filament-tma::notifications.daily.intro.question_count', $questions, ['n' => $questions]),
                    ]),
                },
                'news' => $newsGroups,
                'copies' => $copyGroups,
                'reason' => __('filament-tma::notifications.daily.footer'),
            ]);
    }
}
