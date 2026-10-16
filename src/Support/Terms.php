<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Instance;

/**
 * Les termes clés du module.
 *
 * - **Support** : l'équipe du maître, partout et sans réglage.
 * - **Le nom court du client** (« EDM ») : réglé pour chaque application cliente, dans sa fiche côté maître, et
 *   chez elle dans `filament-tma.instance.short_name` (que `tma:install --short-name` écrit). Il forme « Publier chez
 *   EDM » et « Correspondants EDM ».
 *
 * Tous les autres libellés passent par `__()` : une application en change un dans son fichier de traduction
 * (`lang/fr.json`), sans toucher au module.
 */
final class Terms
{
    /** @var array<string, string|null> Maître : le nom court de chaque application, par identifiant. */
    private static array $shortNames = [];

    public static function support(): string
    {
        return __('Support');
    }

    /**
     * Le nom court du client : celui de cette application cliente ; pour le maître, celui de l'application donnée.
     * Null s'il n'est pas réglé.
     */
    public static function client(?string $instanceUid = null): ?string
    {
        if (Tma::isClient()) {
            $name = config('filament-tma.instance.short_name');

            return filled($name) ? (string) $name : null;
        }

        if ($instanceUid === null) {
            return null;
        }

        if (! array_key_exists($instanceUid, self::$shortNames)) {
            $name = Instance::query()->where('uid', $instanceUid)->value('short_name');
            self::$shortNames[$instanceUid] = filled($name) ? (string) $name : null;
        }

        return self::$shortNames[$instanceUid];
    }

    /**
     * Les correspondants du Support : les personnes d'une application cliente qui ont le droit d'écrire au Support
     * (`tma.ticket.correspond`). Vus du client, « Correspondants du Support » ; vus du maître, « Correspondants
     * EDM ».
     */
    public static function correspondents(?string $instanceUid = null): string
    {
        if (Tma::isClient()) {
            return __('Correspondants du Support');
        }

        $client = $instanceUid === null ? null : self::clientOrInstance($instanceUid);

        return $client === null ? __('Correspondants du client') : __('Correspondants :client', ['client' => $client]);
    }

    /** « Publier chez EDM » : le maître ouvre un ticket ou une tâche à l'application cliente. */
    public static function publish(?string $instanceUid = null): string
    {
        $client = self::client($instanceUid);

        return $client === null ? __('Publier chez le client') : __('Publier chez :client', ['client' => $client]);
    }

    /** Le nom de l'application cliente d'une personne qui n'est pas d'ici : « EDM », sinon le nom de l'application. */
    public static function clientOrInstance(string $instanceUid): string
    {
        return self::client($instanceUid) ?? Instance::query()->where('uid', $instanceUid)->value('name') ?? __('Client');
    }

    /** Après la modification d'une application (son nom court a pu changer). */
    public static function flush(): void
    {
        self::$shortNames = [];
    }
}
