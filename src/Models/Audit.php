<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Enums\Origin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Une ligne du journal d'audit (`Support\Auditor`). Elle ne se modifie ni ne se supprime depuis l'interface. */
class Audit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'tma_audits';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origin' => Origin::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'meta' => 'array',
        ];
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
