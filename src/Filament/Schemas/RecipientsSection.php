<?php

namespace CharlesStOlive\FilamentTma\Filament\Schemas;

use CharlesStOlive\FilamentTma\Contracts\Trackable;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Recipient;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Le bloc « Destinataires » du volet d'une fiche (ticket ou tâche) : les principaux, les secondaires, et « Modifier »,
 * tout au long de la vie du ticket, pour qui peut y écrire. Un membre du Support dans une application cliente les
 * change chez le maître.
 */
final class RecipientsSection
{
    public static function make(): Section
    {
        return Section::make(__('filament-tma::recipients.heading'))->key('recipients')->compact()->columnSpanFull()
            ->headerActions([self::editAction(), Help::title('tma.notifications', __('filament-tma::recipients.help'))])
            ->schema([
                TextEntry::make('primary_recipients')->label(__('filament-tma::recipients.main_short'))
                    ->state(fn (Trackable $record): array => self::names($record, RecipientRole::Primary))
                    ->badge()->color('primary')->placeholder('—'),
                TextEntry::make('secondary_recipients')->label(__('filament-tma::recipients.secondary_short'))
                    ->state(fn (Trackable $record): array => self::names($record, RecipientRole::Secondary))
                    ->badge()->color('gray')->placeholder('—'),
            ]);
    }

    public static function editAction(): Action
    {
        return Action::make('recipients')
            ->label(__('filament-tma::common.edit'))
            ->icon('heroicon-m-pencil-square')
            ->link()
            ->size(Size::Small)
            ->visible(fn (Trackable $record): bool => self::mayEdit($record))
            ->modalHeading(__('filament-tma::recipients.heading'))
            ->modalDescription(__('filament-tma::recipients.description'))
            ->fillForm(fn (Trackable $record): array => app(Recipients::class)->keys($record))
            ->schema(fn (Trackable $record): array => RecipientsFields::make(
                fn (): ?string => $record->clientInstanceUid(),
                project: $record instanceof ProjectTask ? fn (): ?Project => $record->project : null,
            ))
            ->modalSubmitActionLabel(__('filament-tma::common.save'))
            ->action(function (Trackable $record, array $data, Action $action, Component $livewire): void {
                try {
                    app(Recipients::class)->update($record, (array) ($data['primary_recipients'] ?? []), (array) ($data['secondary_recipients'] ?? []), Context::current());
                } catch (AuthorizationException|ValidationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }

                $livewire->dispatch('tma-timeline-changed');
                Notification::make()->success()->title(__('filament-tma::recipients.saved'))->send();
            });
    }

    /**
     * Changer les destinataires : sur un ticket, ceux qui y prennent part (correspondants du Support, membres du
     * Support) ; sur une tâche, qui peut y écrire. Un membre du Support relié à un client le fait dans le maître.
     */
    private static function mayEdit(Trackable $record): bool
    {
        $user = auth()->user();

        if ($user === null || Context::current()->forSubject($record)->isAgent()) {
            return false;
        }

        return $record instanceof Ticket
            ? Addressables::isCorrespondent($user) || Addressables::isSupportMember($user)
            : TmaGate::allows($user, TmaGate::COMMENT, $record);
    }

    /** @return array<int, string> */
    private static function names(Trackable $record, RecipientRole $role): array
    {
        return $record->recipients()->with('actor')->where('role', $role->value)->orderBy('id')->get()
            ->map(fn (Recipient $recipient): string => Addressables::label($recipient->actor))
            ->all();
    }
}
