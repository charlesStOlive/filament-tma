<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une personne qui agit sur un ticket : un utilisateur d'ici (`user_id`) ou d'une autre application (son id là-bas,
 * `remote_id`, dans l'installation `instance_uid`). Le nom et l'e-mail sont une copie, rafraîchie à chaque
 * écriture : l'historique reste lisible même si l'utilisateur disparaît.
 *
 * @property string $instance_uid
 * @property string $remote_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 */
class Actor extends Model
{
    protected $table = 'tma_actors';

    protected $guarded = [];

    /** L'acteur d'un utilisateur de cette application (créé au besoin, nom et e-mail mis à jour). */
    public static function forUser(Authenticatable $user): self
    {
        return self::fromInstance(
            Tma::instanceUid(),
            (string) $user->getAuthIdentifier(),
            (string) ($user->name ?? $user->email ?? $user->getAuthIdentifier()),
            $user->email ?? null,
            $user->getAuthIdentifier(),
        );
    }

    /** L'acteur d'un utilisateur d'une installation donnée, tel qu'elle le décrit. */
    public static function fromInstance(string $instanceUid, string $remoteId, string $name, ?string $email = null, int|string|null $userId = null): self
    {
        $actor = self::firstOrNew(['instance_uid' => $instanceUid, 'remote_id' => $remoteId]);
        $actor->fill(['name' => $name, 'email' => $email, 'user_id' => $userId ?? $actor->user_id]);

        if ($actor->isDirty()) {
            $actor->save();
        }

        return $actor;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Tma::userModel(), 'user_id');
    }

    public function isLocal(): bool
    {
        return Tma::isHere($this->instance_uid);
    }

    public function isUser(?Authenticatable $user): bool
    {
        return $user !== null && $this->isLocal() && (string) $this->user_id === (string) $user->getAuthIdentifier();
    }
}
