<?php

namespace CharlesStOlive\FilamentTma\Workflow\Moves;

use CharlesStOlive\FilamentTma\Enums\Side;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\States\Ticket\Paused;
use CharlesStOlive\FilamentTma\States\Ticket\Submitted;
use CharlesStOlive\FilamentTma\Support\Help;
use CharlesStOlive\FilamentTma\Workflow\Concerns\AnswersTheRequest;
use CharlesStOlive\FilamentTma\Workflow\Concerns\WritesMessages;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Move;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Carbon;

/** Suspendre jusqu'à une date : le ticket reprend tout seul ce jour-là (voir `AutoResume`, `tma:resume-paused`). */
class Pause extends Move
{
    use AnswersTheRequest;
    use WritesMessages;

    public function key(): string
    {
        return 'pause';
    }

    public function label(): string
    {
        return __('Mettre en pause');
    }

    public function color(): string
    {
        return 'gray';
    }

    public function icon(): ?string
    {
        return 'heroicon-m-pause';
    }

    public function from(): array
    {
        return [Submitted::class, InProgress::class];
    }

    public function to(Ticket $ticket): string
    {
        return Paused::class;
    }

    public function sides(): array
    {
        return [Side::Handler];
    }

    public function rules(Ticket $ticket): array
    {
        return [
            'until' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function formSchema(Ticket $ticket): array
    {
        return [
            DateTimePicker::make('until')->label(__('Reprise le'))->required()->minDate(now())->seconds(false)
                ->hintAction(Help::action('tma.tickets.pause', __('Que se passe-t-il à la reprise ?'))),
            $this->reasonField(false, help: ['tma.tickets.pause', __('La pause et sa reprise')]),
        ];
    }

    public function apply(Ticket $ticket, array $data, Context $context): void
    {
        $this->answering($ticket, $context);
        $ticket->paused_until = Carbon::parse($data['until']);
    }
}
