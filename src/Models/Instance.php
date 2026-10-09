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

    /**
     * Le compte de service de l'application, ici : créé au besoin, dédié (sans rôle, jamais un compte de quelqu'un) ;
     * c'est à son jeton que le centre de service reconnaît l'application.
     */
    public function ensureServiceAccount(): \Illuminate\Contracts\Auth\Authenticatable
    {
        if ($this->apiUser !== null) {
            return $this->apiUser;
        }

        $base = 'tma-'.(\Illuminate\Support\Str::slug($this->name) ?: 'application');
        $email = $base.'@tma.local';

        if (\CharlesStOlive\FilamentTma\Support\ServiceAccount::isTaken($email)) {
            $email = $base.'-'.strtolower(substr($this->uid, -6)).'@tma.local';
        }

        $user = \CharlesStOlive\FilamentTma\Support\ServiceAccount::user($email, __('Application :name (TMA)', ['name' => $this->name]));
        $this->forceFill(['api_user_id' => $user->getAuthIdentifier()])->save();
        $this->setRelation('apiUser', $user);

        return $user;
    }

    public function apiUrl(string $path = ''): string
    {
        return rtrim($this->url, '/').'/api/tma/v1/'.ltrim($path, '/');
    }
}
