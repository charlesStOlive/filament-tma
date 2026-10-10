<?php

namespace CharlesStOlive\FilamentTma\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/** Le récapitulatif quotidien d'un utilisateur (`Support\Digest`). */
class DailyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, Collection<int, array<string, mixed>>>  $sections */
    public function __construct(public array $sections) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = collect($this->sections)->sum(fn (Collection $items): int => $items->count());
        $late = collect($this->sections)->sum(fn (Collection $items): int => $items->where('late', true)->count());

        $mail = (new MailMessage)
            ->subject(__('Support : :count élément(s) vous attendent:late', ['count' => $count, 'late' => $late > 0 ? __(' (dont :n en retard)', ['n' => $late]) : '']))
            ->greeting(__('Bonjour,'));

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

        return $mail;
    }
}
