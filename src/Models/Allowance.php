<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Enums\AllowancePeriod;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un forfait de TMA (maître) : `minutes` par période, pour un client — toutes ses applications (`instance_uid`
 * vide) ou une seule — entre `starts_on` et `ends_on`. Voir `Support\AllowanceMeter` pour la consommation.
 *
 * @property AllowancePeriod $period
 * @property int $minutes
 */
class Allowance extends Model
{
    use HasUlids;

    protected $table = 'tma_allowances';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period' => AllowancePeriod::class,
            'minutes' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function client(): MorphTo
    {
        return $this->morphTo();
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instance_uid', 'uid');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(AllowanceAlert::class);
    }

    public function isActiveOn(\DateTimeInterface $day): bool
    {
        return $this->starts_on->lte($day) && ($this->ends_on === null || $this->ends_on->gte($day));
    }

    /** Le forfait couvre les tickets de cette application (la sienne, ou toutes celles de son client). */
    public function covers(string $instanceUid): bool
    {
        if ($this->instance_uid !== null) {
            return $this->instance_uid === $instanceUid;
        }

        $instance = Instance::forUid($instanceUid);

        return $instance !== null && $this->client_type !== null
            && $instance->client_type === $this->client_type && (string) $instance->client_id === (string) $this->client_id;
    }

    /** @return array<int, string> les applications couvertes */
    public function instanceUids(): array
    {
        if ($this->instance_uid !== null) {
            return [$this->instance_uid];
        }

        return Instance::query()->where('client_type', $this->client_type)->where('client_id', $this->client_id)->pluck('uid')->all();
    }

    public function label(): string
    {
        $who = $this->instance?->name ?? $this->client?->{config('filament-tma.models.client_title', 'name')} ?? __('filament-tma::common.allowance');

        return trim($who.($this->contract ? ' — '.$this->contract : ''));
    }
}
