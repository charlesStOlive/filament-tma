<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une application cliente, vue du maître (CDC §6.2). Elle appelle le maître avec le jeton de `apiUser` ; le maître
 * l'appelle avec `token`, émis par elle. Une instance inactive n'est plus reconnue.
 *
 * @property string $uid
 * @property string $name
 * @property string $url
 * @property string|null $token
 * @property bool $is_active
 */
class Instance extends Model
{
    protected $table = 'tma_instances';

    protected $guarded = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_pushed_at' => 'datetime',
        ];
    }

    public function client(): MorphTo
    {
        return $this->morphTo();
    }

    public function apiUser(): BelongsTo
    {
        return $this->belongsTo(Tma::userModel(), 'api_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function forUid(?string $uid): ?self
    {
        return $uid === null ? null : static::query()->where('uid', $uid)->first();
    }

    /** Des tickets ou des tâches ont déjà été reçus de cette application, ou lui sont destinés : son identifiant ne change plus. */
    public function hasTickets(): bool
    {
        return Ticket::withTrashed()->where('source_instance_uid', $this->getOriginal('uid') ?? $this->uid)->exists()
            || ProjectTask::withTrashed()->where('target_instance_uid', $this->getOriginal('uid') ?? $this->uid)->exists();
    }

    public function apiUrl(string $path = ''): string
    {
        return rtrim($this->url, '/').'/api/tma/v1/'.ltrim($path, '/');
    }
}
