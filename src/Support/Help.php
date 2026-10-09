<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentUi\Actions\DocumentationAction;
use Filament\Actions\Action;
use Throwable;

/**
 * Le « ? » bleu des écrans du module (`DocumentationAction` de filament-ui) : il ouvre une page de l'aide
 * (docs/knowledge-base/tma) dans une fenêtre.
 *
 *     TextInput::make('uid')->hintAction(Help::action('tma.demarrer.identifiant', __('À quoi sert l’identifiant ?')))
 *     Section::make(...)->headerActions([Help::title('tma.demarrer.jetons', __('Les comptes et leurs jetons'))])
 *
 * Une application sans base de connaissances (pas de KnowledgeBaseCompanionPlugin, pages non publiées) n'a simplement
 * pas de « ? » : l'action est cachée, l'écran ne change pas.
 */
final class Help
{
    /** Le « ? » seul, juste après le libellé ; `$label` en infobulle. */
    public static function action(string $page, string $label): Action
    {
        try {
            return DocumentationAction::forDocumentable($page)->label($label);
        } catch (Throwable) {
            return Action::make('tma_help_'.str_replace(['.', '-'], '_', $page))->hidden();
        }
    }

    /** Le « ? » avec son titre en toutes lettres, au bout de la ligne : pour l'en-tête d'une section. */
    public static function title(string $page, string $label): Action
    {
        $action = self::action($page, $label);

        return $action instanceof DocumentationAction ? $action->fullTitle() : $action;
    }
}
