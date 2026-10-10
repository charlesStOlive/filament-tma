<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Client : un utilisateur d'ici qui est aussi membre du Support. Sur un ticket ou une tâche du Support, il agit
 * dans le maître, en son nom de là-bas, par son jeton personnel (`token`, émis par le maître et limité à cette
 * application) — jamais comme demandeur.
 *
 * @property string $master_uid
 * @property string $token
 */
class Agent extends Model
{
    protected $table = 'tma_agents';

    protected $guarded = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'linked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Tma::userModel(), 'user_id');
    }

    public static function for(?Authenticatable $user): ?self
    {
        if ($user === null || ! Tma::isClient()) {
            return null;
        }

        return static::query()->where('user_id', $user->getAuthIdentifier())->first();
    }
}
