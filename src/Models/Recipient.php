<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un destinataire d'un ticket ou d'une tâche : une personne (`Actor`, d'ici ou de l'autre application) et son rôle.
 * Chaque application prévient les siens ; la liste voyage avec le ticket (`Workflow\Recipients`).
 *
 * @property RecipientRole $role
 */
class Recipient extends Model
{
    protected $table = 'tma_recipients';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['role' => RecipientRole::class];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_id');
    }
}
