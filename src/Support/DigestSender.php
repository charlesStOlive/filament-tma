<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentPermissionManager\UserOptions\Contracts\HasUserOptions;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use CharlesStOlive\FilamentTma\Notifications\WeeklyDigest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Envoie les bilans. Personne ne reçoit un courriel vide, ni un bilan qu'il ne veut pas (`NoticeChannels`) : ce qui
 * avait été gardé pour lui avant qu'il le refuse est seulement noté.
 *
 * - **Le bilan du matin** (`tma:digest`) : ce qui attend chacun sur ses tickets (`Digest`) et ce qui a été mis de côté
 *   pour lui (`Notice::daily()`). Le matin, tout le monde ; à la demande (« Envoyer maintenant »,
 *   `tma:notify --grouped`), seulement qui a des notifications en attente.
 * - **Le bilan de la semaine** (`tma:digest --weekly`) : ce qui est arrivé aux tâches projet de chacun
 *   (`Notice::weekly()`), seulement à qui en a.
 */
final class DigestSender
{
    /**
     * @param  \Closure(Authenticatable, int, int): void|null  $report  appelé pour chaque destinataire (éléments qui l'attendent, nouveautés)
     * @return array{recipients: int, notices: int}
     */
    public static function send(bool $pendingOnly = false, bool $dryRun = false, ?\Closure $report = null, bool $weekly = false): array
    {
        $recipients = 0;
        $notices = 0;
        $model = Tma::userModel();
        $scope = fn (Builder $query): Builder => $weekly ? $query->weekly() : $query->daily();

        $users = $pendingOnly || $weekly
            ? $model::query()->whereIn((new $model)->getKeyName(), $scope(Notice::query()->pending())->distinct()->pluck('user_id'))
            : $model::query();

        if (is_subclass_of($model, HasUserOptions::class)) {
            $users->with('optionGroups');
        }

        $users->each(function (Authenticatable $user) use ($dryRun, $report, $weekly, $scope, &$recipients, &$notices): void {
            if (! ($weekly ? NoticeChannels::weekly($user) : NoticeChannels::digest($user))) {
                if (! $dryRun) {
                    $scope(Notice::query()->pending()->for($user))->update(['mode' => NoticeMode::Skipped->value, 'sent_at' => now()]);
                }

                return;
            }

            $sections = $weekly ? [] : Digest::for($user);
            $pending = $scope(Notice::query()->pending()->for($user))->with('subject')->orderBy('id')->get();

            if ($sections === [] && $pending->isEmpty()) {
                return;
            }

            $recipients++;
            $notices += $pending->count();

            if ($report !== null) {
                $report($user, collect($sections)->sum(fn ($items) => $items->count()), $pending->count());
            }

            if ($dryRun || ! method_exists($user, 'notify')) {
                return;
            }

            $user->notify($weekly ? new WeeklyDigest($pending) : new DailyDigest($sections, $pending));
            Notice::query()->whereKey($pending->modelKeys())->update(['sent_at' => now()]);
        });

        return ['recipients' => $recipients, 'notices' => $notices];
    }

    /** @return array{recipients: int, notices: int} ce qui attend le bilan du matin */
    public static function pendingCounts(): array
    {
        return [
            'recipients' => Notice::query()->pending()->daily()->distinct()->count('user_id'),
            'notices' => Notice::query()->pending()->daily()->count(),
        ];
    }
}
