<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * L'historique des changements d'état (CDC §6.8) : le mouvement fait (`move`, la clé de `Workflow\Move`), l'état
 * d'avant et d'après, l'auteur, l'application et l'origine, le motif.
 *
 * @property string $move
 * @property string|null $from_state
 * @property string $to_state
 * @property Origin $origin
 * @property string|null $reason
 */
class StateTransition extends Model
{
    public const UPDATED_AT = null;

    use HasUlids;

    protected $table = 'tma_state_transitions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'origin' => Origin::class,
            'meta' => 'array',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function booted(): void
    {
        static::creating(function (StateTransition $transition): void {
            $transition->origin_instance_uid ??= Tma::instanceUid();
        });
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
