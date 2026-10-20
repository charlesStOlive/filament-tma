<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Enums\NoticeEvent;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource;
use CharlesStOlive\FilamentTma\Support\TicketUrl;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Ce qui a été dit à une personne d'ici (`Support\Notifier`) : envoyé tout de suite (`sent_at` posé à l'écriture),
 * ou mis de côté pour son prochain bilan (`sent_at` vide, `pending()`) — celui du matin (`daily()`) ou celui de la
 * semaine (`weekly()`, les tâches). Le texte est écrit à l'événement (`data` : `ref`, `title`, `line`, `excerpt`, et
 * `role`, `tone`, `digest`, `headline`, `action`…), pour rester lisible si le ticket change ou disparaît.
 *
 * @property string $user_id
 * @property NoticeEvent $event
 * @property string $key
 * @property NoticeMode $mode
 * @property array<string, mixed> $data
 * @property Carbon|null $sent_at
 */
class Notice extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $table = 'tma_notices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event' => NoticeEvent::class,
            'mode' => NoticeMode::class,
            'data' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('sent_at');
    }

    /** Pour le bilan du matin (les notifications écrites avant les deux bilans y vont aussi). */
    public function scopeDaily(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q->where('data->digest', 'digest')->orWhereNull('data->digest'));
    }

    /** Pour le bilan de la semaine : ce qui arrive aux tâches projet. */
    public function scopeWeekly(Builder $query): Builder
    {
        return $query->where('data->digest', 'weekly');
    }

    public function scopeFor(Builder $query, Authenticatable $user): Builder
    {
        return $query->where('user_id', (string) $user->getAuthIdentifier());
    }

    /** L'adresse de la fiche, tant qu'elle existe. */
    public function url(): ?string
    {
        $subject = $this->subject;

        if ($subject === null || (method_exists($subject, 'trashed') && $subject->trashed())) {
            return null;
        }

        return rescue(fn (): string => $subject instanceof Ticket
            ? TicketUrl::view($subject)
            : TaskResource::getUrl('view', ['record' => $subject], panel: Filament::getCurrentOrDefaultPanel()?->getId()), null, false);
    }

    /** Les notifications envoyées depuis plus de `notifications.keep_days` jours (`model:prune`). */
    public function prunable(): Builder
    {
        return static::query()->whereNotNull('sent_at')
            ->where('sent_at', '<', now()->subDays((int) config('filament-tma.notifications.keep_days', 90)));
    }
}
