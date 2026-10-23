<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Notifications\Concerns\GroupsNotices;
use CharlesStOlive\FilamentTma\Support\Duration;
use CharlesStOlive\FilamentTma\Support\MailBrand;
use CharlesStOlive\FilamentTma\Workflow\Questions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Le bilan de la semaine des tâches projet (`resources/views/mail/weekly.blade.php`), pour information : par projet,
 * chaque tâche (son état, son échéance, le temps passé) et ce qui lui est arrivé ; en bas, « En copie », là où l'on
 * n'est que destinataire secondaire. Une question du Support qui attend encore la personne est signalée en tête et sur
 * sa tâche : elle est dans son bilan du matin (`Workflow\Questions`).
 */
class WeeklyDigest extends Notification implements ShouldQueue
{
    use GroupsNotices;
    use Queueable;

    /** @param  Collection<int, Notice>  $pending */
    public function __construct(public Collection $pending)
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
        [$own, $copies] = self::splitCopies($this->pending);
        $asked = Questions::openFor($user)->countBy('subject_id');
        $describe = fn (array $group): array => [...$group, 'facts' => $this->facts($group['subject'], $user, (int) ($asked[$group['subject']?->getKey()] ?? 0))];

        $projects = collect(self::groups($own))
            ->map($describe)
            ->groupBy(fn (array $task): string => (string) ($task['project'] ?? ''))
            ->map(fn (Collection $tasks, string $project): array => ['title' => $project !== '' ? $project : __('Sans projet'), 'tasks' => $tasks->all()])
            ->values()->all();
        $copyGroups = array_map($describe, self::groups($copies));
        $count = count(self::groups($own)) + count($copyGroups);

        return (new MailMessage)
            ->subject(trans_choice('Bilan de la semaine : :n tâche de vos projets|Bilan de la semaine : :n tâches de vos projets', $count, ['n' => $count]))
            ->view(['filament-tma::mail.weekly', 'filament-tma::mail.weekly-text'], [
                'brand' => MailBrand::for('task'),
                'kicker' => __('Tâches projet · bilan de la semaine'),
                'heading' => __('Ce qui a avancé sur vos projets'),
                'preheader' => __('Pour information : aucune action n’est attendue de votre part.'),
                'boxText' => $asked->isEmpty()
                    ? __('Aucune action n’est attendue de votre part.')
                    : trans_choice(':n question du Support attend votre réponse (elle est dans votre bilan du matin) ; le reste est pour information.|:n questions du Support attendent votre réponse (elles sont dans votre bilan du matin) ; le reste est pour information.', $asked->sum(), ['n' => $asked->sum()]),
                'projects' => $projects,
                'copies' => $copyGroups,
                'reason' => __('Vous recevez le bilan de la semaine des tâches projet.'),
            ]);
    }

    /** @return array<string, string> l'état, l'échéance et le temps passé d'une tâche, tels qu'aujourd'hui, et les questions qui attendent la personne */
    private function facts(mixed $task, ?Authenticatable $user, int $questions = 0): array
    {
        if (! $task instanceof ProjectTask) {
            return [];
        }

        return array_filter([
            __('État') => $task->state->getLabel(),
            __('Échéance') => $task->due_on?->format('d/m/Y'),
            __('Temps passé') => (int) $task->time_total_minutes > 0 ? Duration::format((int) $task->time_total_minutes) : null,
            __('À vous de répondre') => $questions > 0 ? trans_choice(':n question|:n questions', $questions, ['n' => $questions]) : null,
        ], fn (?string $value): bool => filled($value));
    }
}
