<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Audit;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

/**
 * Le journal d'audit (CDC §23). Un appel par action sensible ; l'auteur et l'origine viennent du contexte quand on
 * le connaît (`Workflow\Context`), sinon de la requête : utilisateur connecté (session ou jeton), et l'origine
 * déduite (API pour /api, commande en console, interface sinon).
 *
 * Les secrets ne sont jamais écrits (`MASKED`).
 */
final class Auditor
{
    /** Les champs dont la valeur n'est jamais gardée. */
    private const MASKED = ['token', 'password', 'remember_token', 'content'];

    private static ?Context $context = null;

    /** Exécute `$callback` en attribuant ses écritures à ce contexte (une commande, l'API de l'autre application…). */
    public static function as(Context $context, \Closure $callback): mixed
    {
        $previous = self::$context;
        self::$context = $context;

        try {
            return $callback();
        } finally {
            self::$context = $previous;
        }
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Model $subject = null, ?array $old = null, ?array $new = null, array $meta = [], ?Context $context = null): Audit
    {
        $context ??= self::$context;

        return Audit::create([
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => self::label($subject),
            'actor_id' => $context?->actor?->getKey() ?? self::currentActor()?->getKey(),
            'instance_uid' => $context?->instanceUid ?? Tma::instanceUid(),
            'origin' => $context?->origin ?? self::currentOrigin(),
            'old_values' => self::mask($old),
            'new_values' => self::mask($new),
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    /** Ce qui a changé dans un modèle qu'on vient d'enregistrer : `[avant, après]`, sans les dates techniques. */
    public static function changes(Model $model): array
    {
        $ignored = ['updated_at', 'last_activity_at', 'synced_at', 'sync_status', 'sync_version', 'last_sync_error', 'auto_close_warned_at'];
        $new = collect($model->getChanges())->except($ignored)->all();
        $old = collect($new)->mapWithKeys(fn ($value, string $key): array => [$key => $model->getOriginal($key)])->all();

        return [$old, $new];
    }

    private static function currentActor(): ?Actor
    {
        $user = Auth::user() ?? rescue(fn () => Auth::guard(config('filament-tma.api.guard', 'api'))->user(), null, false);

        return $user !== null ? Actor::forUser($user) : null;
    }

    private static function currentOrigin(): Origin
    {
        if (App::runningInConsole() && ! App::runningUnitTests()) {
            return Origin::Command;
        }

        return request()->is('api/*') ? Origin::Api : Origin::Ui;
    }

    /** @param  array<string, mixed>|null  $values */
    private static function mask(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::MASKED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = $values[$key] === null ? null : '••••';
            }
        }

        return $values;
    }

    private static function label(?Model $subject): ?string
    {
        if ($subject === null) {
            return null;
        }

        foreach (['number', 'name', 'title', 'ulid'] as $attribute) {
            if (filled($subject->getAttribute($attribute))) {
                return (string) $subject->getAttribute($attribute);
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }
}
