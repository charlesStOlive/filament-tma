<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Models\Instance;

/**
 * Les termes clés du module.
 *
 * - **Support** : l'équipe du maître, partout et sans réglage.
 * - **Le nom court du client** (« EDM ») : réglé pour chaque application cliente, dans sa fiche côté maître, et
 *   chez elle dans `filament-tma.instance.short_name` (que `tma:install --short-name` écrit). Il forme « Équipe EDM »
 *   (l'équipe locale, qui traite les demandes sans le Support) et « Publier chez EDM ».
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

    /** L'équipe locale du client, qui traite une demande sans le Support : « Équipe EDM ». */
    public static function team(?string $instanceUid = null): string
    {
        $client = self::client($instanceUid);

        return $client === null ? __('Équipe locale') : __('Équipe :client', ['client' => $client]);
    }

    /** « Confier à l'équipe EDM » : un brouillon part à l'équipe locale, sans le Support. */
    public static function submit(?string $instanceUid = null): string
    {
        $client = self::client($instanceUid);

        return $client === null ? __('Confier à l’équipe locale') : __('Confier à l’équipe :client', ['client' => $client]);
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
