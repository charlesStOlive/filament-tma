<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentPermissionManager\UserOptions\Contracts\HasUserOptions;
use CharlesStOlive\FilamentTma\Enums\NoticeMode;
use CharlesStOlive\FilamentTma\Models\Notice;
use CharlesStOlive\FilamentTma\Notifications\DailyDigest;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Envoie les récapitulatifs : à chacun ce qui l'attend (`Digest`) et ce qui a été mis de côté pour lui depuis le
 * dernier envoi (`Notice::pending()`), qui part alors comme envoyé. Personne ne reçoit un courriel vide, ni qui ne
 * veut pas du bilan (`NoticeChannels::digest()`) : ce qui avait été gardé pour lui avant qu'il le refuse est
 * seulement noté.
 *
 * Le matin (`tma:digest`), tout le monde ; à la demande (bouton « Envoyer maintenant », `tma:notify --grouped`),
 * seulement qui a des notifications en attente.
 */
final class DigestSender
{
    /**
     * @param  \Closure(Authenticatable, int, int): void|null  $report  appelé pour chaque destinataire (éléments qui l'attendent, nouveautés)
     * @return array{recipients: int, notices: int}
     */
    public static function send(bool $pendingOnly = false, bool $dryRun = false, ?\Closure $report = null): array
    {
        $recipients = 0;
        $notices = 0;
        $model = Tma::userModel();

        $users = $pendingOnly
            ? $model::query()->whereIn((new $model)->getKeyName(), Notice::query()->pending()->distinct()->pluck('user_id'))
            : $model::query();

        if (is_subclass_of($model, HasUserOptions::class)) {
            $users->with('optionGroups');
        }

        $users->each(function (Authenticatable $user) use ($dryRun, $report, &$recipients, &$notices): void {
            if (! NoticeChannels::digest($user)) {
                if (! $dryRun) {
                    Notice::query()->pending()->for($user)->update(['mode' => NoticeMode::Skipped->value, 'sent_at' => now()]);
                }

                return;
            }

            $sections = Digest::for($user);
            $pending = Notice::query()->pending()->for($user)->with('subject')->orderBy('id')->get();

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

            $user->notify(new DailyDigest($sections, $pending));
            Notice::query()->whereKey($pending->modelKeys())->update(['sent_at' => now()]);
        });

        return ['recipients' => $recipients, 'notices' => $notices];
    }

    /** @return array{recipients: int, notices: int} ce qui attend un envoi groupé */
    public static function pendingCounts(): array
    {
        return [
            'recipients' => Notice::query()->pending()->distinct()->count('user_id'),
            'notices' => Notice::query()->pending()->count(),
        ];
    }
}
