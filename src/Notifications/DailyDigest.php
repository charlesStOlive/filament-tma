<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use CharlesStOlive\FilamentTma\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Le récapitulatif d'un utilisateur : ce qui l'attend (`Support\Digest`), puis ce qui s'est passé depuis le dernier
 * envoi (les notifications mises de côté pour lui, `Notice::pending()`), regroupé par ticket.
 */
class DailyDigest extends Notification implements ShouldQueue
{
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
        $pending = $this->pending ?? collect();
        $count = collect($this->sections)->sum(fn (Collection $items): int => $items->count());
        $late = collect($this->sections)->sum(fn (Collection $items): int => $items->where('late', true)->count());

        $subject = $count > 0
            ? __('Support : :count élément(s) vous attendent:late', ['count' => $count, 'late' => $late > 0 ? __(' (dont :n en retard)', ['n' => $late]) : ''])
            : __('Support : :n nouveauté(s)', ['n' => $pending->count()]);

        if ($count > 0 && $pending->isNotEmpty()) {
            $subject .= __(', :n nouveauté(s)', ['n' => $pending->count()]);
        }

        $mail = (new MailMessage)->subject($subject)->greeting(__('Bonjour,'));

        $titles = [
            'answer' => __('Vos demandes qui attendent votre réponse ou votre confirmation'),
            'take' => __('À prendre en charge'),
            'mine' => __('En cours chez vous'),
            'resume' => __('Reprennent aujourd’hui'),
        ];

        foreach ($this->sections as $key => $items) {
            $list = $items->map(function (array $item): string {
                $label = e("{$item['ref']} — {$item['title']}").' <em>('.e($item['state']).')</em>'.($item['late'] ? ' <strong>'.e(__('en retard')).'</strong>' : '');

                return '<li>'.($item['url'] ? '<a href="'.e($item['url']).'">'.$label.'</a>' : $label).'</li>';
            })->implode('');

            $mail->line(new HtmlString('<strong>'.e($titles[$key] ?? $key).'</strong><ul>'.$list.'</ul>'));
        }

        if ($pending->isNotEmpty()) {
            $mail->line(new HtmlString('<strong>'.e(__('Depuis le dernier envoi')).'</strong>'.$this->pendingList($pending)));
        }

        return $mail;
    }

    /** Les notifications mises de côté, par ticket ou tâche, dans l'ordre où elles sont arrivées. */
    private function pendingList(Collection $pending): string
    {
        return '<ul>'.$pending->sortBy('id')
            ->groupBy(fn (Notice $notice): string => $notice->subject_type.':'.$notice->subject_id)
            ->map(function (Collection $notices): string {
                $first = $notices->first();
                $url = $first->url();
                $label = e("{$first->data['ref']} — {$first->data['title']}");
                $lines = $notices->map(fn (Notice $notice): string => '<li>'.e($notice->data['line'])
                    .(filled($notice->data['excerpt'] ?? null) ? ' <em>« '.e($notice->data['excerpt']).' »</em>' : '').'</li>')->implode('');

                return '<li>'.($url ? '<a href="'.e($url).'">'.$label.'</a>' : $label).'<ul>'.$lines.'</ul></li>';
            })->implode('').'</ul>';
    }
}
