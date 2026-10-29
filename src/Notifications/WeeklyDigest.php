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
            ->map(fn (Collection $tasks, string $project): array => ['title' => $project !== '' ? $project : __('filament-tma::common.no_project'), 'tasks' => $tasks->all()])
            ->values()->all();
        $copyGroups = array_map($describe, self::groups($copies));
        $count = count(self::groups($own)) + count($copyGroups);

        return (new MailMessage)
            ->subject(trans_choice('filament-tma::notifications.weekly.subject', $count, ['n' => $count]))
            ->view(['filament-tma::mail.weekly', 'filament-tma::mail.weekly-text'], [
                'brand' => MailBrand::for('task'),
                'kicker' => __('filament-tma::notifications.weekly.kicker'),
                'heading' => __('filament-tma::notifications.weekly.title'),
                'preheader' => __('filament-tma::notifications.weekly.intro'),
                'boxText' => $asked->isEmpty()
                    ? __('filament-tma::notifications.weekly.no_action')
                    : trans_choice('filament-tma::notifications.weekly.questions', $asked->sum(), ['n' => $asked->sum()]),
                'projects' => $projects,
                'copies' => $copyGroups,
                'reason' => __('filament-tma::notifications.weekly.footer'),
            ]);
    }

    /** @return array<string, string> l'état, l'échéance et le temps passé d'une tâche, tels qu'aujourd'hui, et les questions qui attendent la personne */
    private function facts(mixed $task, ?Authenticatable $user, int $questions = 0): array
    {
        if (! $task instanceof ProjectTask) {
            return [];
        }

        return array_filter([
            __('filament-tma::common.state') => $task->state->getLabel(),
            __('filament-tma::notifications.weekly.due') => $task->due_on?->format('d/m/Y'),
            __('filament-tma::common.time_spent') => (int) $task->time_total_minutes > 0 ? Duration::format((int) $task->time_total_minutes, Duration::AUTO) : null,
            __('filament-tma::notifications.weekly.your_turn') => $questions > 0 ? trans_choice('filament-tma::notifications.weekly.question_count', $questions, ['n' => $questions]) : null,
        ], fn (?string $value): bool => filled($value));
    }
}
