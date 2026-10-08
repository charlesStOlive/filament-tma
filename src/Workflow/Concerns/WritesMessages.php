<?php

namespace CharlesStOlive\FilamentTma\Workflow\Concerns;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Html;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;

/** Les messages qu'un mouvement demande (la question, la réponse, le motif) et qu'il écrit dans la chronologie. */
trait WritesMessages
{
    protected function messageField(string $name, string $label, bool $required = true): RichEditor
    {
        return RichEditor::make($name)
            ->label($label)
            ->required($required)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                ['undo', 'redo'],
            ]);
    }

    protected function reasonField(bool $required = true, ?string $label = null): Textarea
    {
        return Textarea::make('reason')
            ->label($label ?? __('Motif'))
            ->required($required)
            ->rows(3)
            ->maxLength(2000);
    }

    /** @return array<int, mixed> */
    protected function messageRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:500000',
            function (string $attribute, mixed $value, Closure $fail) use ($required): void {
                if ($required && Html::isBlank((string) $value)) {
                    $fail(__('Le message est vide.'));
                }
            },
        ];
    }

    /** Écrit un commentaire (partagé avec l'autre partie) si le message n'est pas vide. */
    protected function writeComment(Ticket $ticket, ?string $body, Context $context, array $meta = [], ?Entry $parent = null): ?Entry
    {
        if (Html::isBlank($body) || $context->actor === null) {
            return null;
        }

        return app(Timeline::class)->write($ticket, EntryType::Comment, $body, $context, $parent, $meta);
    }
}
