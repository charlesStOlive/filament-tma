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
 *   envoi ;
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
            'answer' => __('Vos demandes qui attendent votre réponse ou votre confirmation'),
            'take' => __('Nouvelles demandes sans contact'),
            'mine' => __('En cours chez vous'),
            'resume' => __('Reprennent aujourd’hui'),
        ];

        $todo = collect($this->sections)->map(fn (Collection $items, string $key): array => [
            'title' => $titles[$key] ?? $key,
            'items' => $items->map(fn (array $item): array => [
                ...$item,
                'since' => isset($item['since']) ? Dates::format($item['since'], 'd/m/Y', $user) : null,
                'closes_on' => isset($item['closes_on']) ? Dates::format($item['closes_on'], 'd/m/Y', $user) : null,
                'lines' => self::lines($attached->filter(fn (Notice $notice): bool => ($notice->data['ref'] ?? null) === $item['ref'])),
            ])->values()->all(),
        ])->values()->all();

        $count = collect($this->sections)->sum(fn (Collection $items): int => $items->count());
        $late = collect($this->sections)->sum(fn (Collection $items): int => $items->where('late', true)->count());
        $newsGroups = self::groups($news);
        $copyGroups = self::groups($copies);

        $summary = collect([
            $count > 0 ? trans_choice(':n à faire|:n à faire', $count, ['n' => $count]).($late > 0 ? __(' (dont :n en retard)', ['n' => $late]) : '') : null,
            $newsGroups !== [] ? trans_choice(':n nouveauté|:n nouveautés', count($newsGroups), ['n' => count($newsGroups)]) : null,
            $copyGroups !== [] ? trans_choice(':n en copie|:n en copie', count($copyGroups), ['n' => count($copyGroups)]) : null,
        ])->filter()->implode(', ');

        return (new MailMessage)
            ->subject(__('Bilan du matin : :summary', ['summary' => $summary]))
            ->view(['filament-tma::mail.digest', 'filament-tma::mail.digest-text'], [
                'brand' => MailBrand::for('ticket'),
                'kicker' => __('Support · :date', ['date' => Dates::format(now(), 'd/m/Y', $user)]),
                'heading' => __('Votre bilan du matin'),
                'preheader' => ucfirst($summary).'.',
                'todo' => $todo,
                'news' => $newsGroups,
                'copies' => $copyGroups,
                'reason' => __('Vous recevez le bilan du matin des tickets du Support.'),
            ]);
    }
}
