<?php

namespace CharlesStOlive\FilamentTma\Filament\Concerns;

use CharlesStOlive\FilamentPrism\Filament\Actions\CorrectionAction;
use CharlesStOlive\FilamentPrism\Models\AiInteraction;
use CharlesStOlive\FilamentPrism\Support\CorrectableField;
use CharlesStOlive\FilamentPrism\Support\FieldsCorrectionSubject;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Support\Orthography;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

/**
 * La correction orthographique par l'IA (facultative, voir `Orthography`) des textes d'une page : le nouveau message
 * (`HasComposer`), le formulaire d'un ticket ou d'une tâche. Le bouton ouvre la revue de filament-prism : rien ne part
 * à l'IA avant « Envoyer », et « Appliquer » remet le texte corrigé dans son champ, comme une frappe au clavier, encore
 * à envoyer ou à enregistrer.
 *
 * La demande se rattache à l'enregistrement de la page, ou à l'utilisateur sur une page de création ; sa clé dit de
 * quel schéma elle vient. À l'application, seules comptent les valeurs de la demande en base, et seulement si elle est
 * de cet utilisateur, sur cette page : l'événement Livewire peut venir du navigateur.
 */
trait HasOrthography
{
    /** Les schémas d'une page dont on corrige les champs. */
    private const ORTHOGRAPHY_SCHEMAS = ['composer', 'form'];

    /**
     * Le bouton « Orthographe » pour des champs d'un schéma de la page, nom => libellé. Partent ceux qui se montrent,
     * modifiables et non vides. Sans filament-prism, aucun bouton.
     *
     * @param  array<string, string>  $fields
     * @return array<int, Action>
     */
    protected function orthographyActions(string $schema, array $fields): array
    {
        if (! Orthography::installed()) {
            return [];
        }

        return [
            CorrectionAction::make('orthography_'.$schema)
                ->taskKey(Orthography::task())
                ->color('gray')
                ->autoApply(false)
                ->visible(fn (): bool => Orthography::available())
                ->trackable(fn (): ?Model => $this->getRecord())
                ->correctable(function () use ($schema, $fields): FieldsCorrectionSubject {
                    $texts = $this->orthographyTexts($schema, $fields);

                    return FieldsCorrectionSubject::make(
                        model: $this->orthographyOwner(),
                        key: $this->orthographyKey($schema),
                        fields: collect($texts)
                            ->map(fn (array $text, string $name): CorrectableField => CorrectableField::make($name)->label($text['label'])->html($text['html']))
                            ->values()
                            ->all(),
                        get: fn (): array => array_map(fn (array $text): string => $text['value'], $texts),
                        label: $this->orthographyLabel($schema),
                    );
                }),
        ];
    }

    /**
     * « Appliquer » dans la revue : le texte corrigé revient dans ses champs. `$values` ne dit que quels champs ont été
     * choisis ; le texte est relu dans la demande.
     *
     * @param  array<string, mixed>  $values
     */
    #[On('filament-prism:correction-applied')]
    public function applyOrthography(int $interactionId, array $values): void
    {
        $interaction = AiInteraction::find($interactionId);
        $schema = $interaction === null ? null : $this->orthographySchemaOf($interaction);

        if ($schema === null
            || (string) $interaction->user_id !== (string) auth()->id()
            || ! $interaction->correctable?->is($this->orthographyOwner())) {
            return;
        }

        foreach (array_intersect_key($interaction->output ?? [], $values) as $name => $text) {
            $component = $this->getSchema($schema)?->getComponent((string) $name);

            // Le cast du champ fait le reste : un RichEditor reprend le HTML corrigé dans son document.
            if ($component instanceof Field && ! $component->isDisabled() && is_string($text)) {
                $component->state($text);
            }
        }

        $this->unmountAction();

        Notification::make()->success()
            ->title(__('filament-tma::messages.orthography.applied'))
            ->body(__('filament-tma::messages.orthography.applied_body'))
            ->send();
    }

    /**
     * Les champs à corriger, avec leur texte : celui d'un RichEditor en HTML (son état Livewire est un document
     * TipTap, que `getState()` convertit).
     *
     * @param  array<string, string>  $fields
     * @return array<string, array{label: string, html: bool, value: string}>
     */
    private function orthographyTexts(string $schema, array $fields): array
    {
        $texts = [];

        foreach ($fields as $name => $label) {
            $component = $this->getSchema($schema)?->getComponent($name);

            if (! $component instanceof Field || $component->isDisabled()) {
                continue;
            }

            $html = $component instanceof RichEditor;
            $value = (string) ($component->getState() ?? '');

            if ($html ? Html::isBlank($value) : blank($value)) {
                continue;
            }

            $texts[$name] = ['label' => $label, 'html' => $html, 'value' => $value];
        }

        return $texts;
    }

    /** Ce à quoi la demande se rattache : l'enregistrement de la page, ou l'utilisateur avant la création. */
    private function orthographyOwner(): Model
    {
        return $this->getRecord() ?? auth()->user();
    }

    /** `tma:tickets:composer`, `tma:tasks:form`… */
    private function orthographyKey(string $schema): string
    {
        return 'tma:'.Str::afterLast(static::getResource()::getSlug(), '/').':'.$schema;
    }

    private function orthographySchemaOf(AiInteraction $interaction): ?string
    {
        foreach (self::ORTHOGRAPHY_SCHEMAS as $schema) {
            if ($interaction->subject_key === $this->orthographyKey($schema)) {
                return $schema;
            }
        }

        return null;
    }

    /** Ce qu'on corrige, en clair, pour le volet des demandes IA : « Message — DES-0001 », « Ticket DES-0001 ». */
    private function orthographyLabel(string $schema): string
    {
        $record = $this->getRecord();
        $model = Str::ucfirst(static::getResource()::getModelLabel());

        return match (true) {
            $record === null => $model.' — '.__('filament-tma::messages.orthography.creation'),
            $schema === 'composer' => __('filament-tma::messages.orthography.message_subject', ['number' => $record->number]),
            default => $model.' '.$record->number,
        };
    }
}
