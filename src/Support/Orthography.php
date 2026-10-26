<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentPrism\Filament\Actions\CorrectionAction;
use CharlesStOlive\FilamentPrism\Registry\AiTaskRegistry;
use CharlesStOlive\FilamentPrism\Services\CorrectionService;

/**
 * La correction orthographique par l'IA, facultative : elle passe par charlesstolive/filament-prism, que le plugin ne
 * demande pas (`suggest`). Le bouton « Orthographe » ne s'offre que si tout est là : prism installé, le réglage
 * `filament-tma.ai.orthography` allumé, la tâche de correction connue de prism et la clé API de son fournisseur
 * (`prism.providers.<fournisseur>.api_key`). Sans clé, chez un client par exemple, rien ne s'affiche.
 *
 * Le droit, lui, existe dès que prism est installé, même sans clé : `tma.ticket.ai.orthography` et
 * `tma.task.ai.orthography` (famille `ai`). Il se donne d'avance, et le jour où la clé arrive, qui l'a s'en sert.
 * filament-prism le vérifie sur l'action (`AiPermissions`) ; sans gestionnaire de permissions, la correction reste
 * ouverte à qui peut écrire.
 */
final class Orthography
{
    /** La famille des droits de l'IA, comme dans filament-prism (`AiPermissions::FAMILY`). */
    public const FAMILY = 'ai';

    /** Une clé de traduction : filament-permission-manager la traduit à l'affichage. */
    public const FAMILY_LABEL = 'filament-tma::permissions.families.ai';

    public static function installed(): bool
    {
        return class_exists(CorrectionAction::class);
    }

    /** La tâche de filament-prism qui corrige : `orthography`, ou une tâche de l'application. */
    public static function task(): string
    {
        return (string) config('filament-tma.ai.task', 'orthography');
    }

    /** La correction peut servir maintenant ; le droit de chacun se vérifie à part, sur l'action. */
    public static function available(): bool
    {
        if (! self::installed() || ! config('filament-tma.ai.orthography', true)) {
            return false;
        }

        $tasks = app(AiTaskRegistry::class);

        return $tasks->has(self::task())
            && app(CorrectionService::class)->providerIsConfigured($tasks->get(self::task())->provider());
    }

    /**
     * Le droit, que déclarent les Resources des tickets et des tâches.
     *
     * @return array<string, string>
     */
    public static function permissionActions(): array
    {
        return self::installed() ? [self::FAMILY.'.'.self::task() => 'filament-tma::permissions.orthography'] : [];
    }
}
