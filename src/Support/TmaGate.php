<?php

namespace CharlesStOlive\FilamentTma\Support;

use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Ticket;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Les droits propres du plugin, au format de filament-permission-manager sans en dépendre : la Resource des tickets
 * déclare ses actions (`comment`, `note`, `workflow.take-charge`…), filament-permission-manager crée les permissions
 * `tma.ticket.{action}` et une ability `{TicketResource}.{action}` qui les vérifie. Sans lui, l'ability n'existe pas
 * et l'action reste ouverte à qui voit le ticket, comme dans les autres plugins.
 */
final class TmaGate
{
    public const COMMENT = 'comment';

    public const NOTE = 'note';

    public const SHARE_NOTE = 'share-note';

    public const TIME = 'time';

    public const SYNC = 'sync';

    public const WORKFLOW_FAMILY = 'workflow';

    /** @var array<class-string<Model>, class-string> la Resource qui déclare les droits de chaque objet */
    private static array $resources = [];

    /** @param  class-string<Model>  $model */
    public static function registerResource(string $model, string $resource): void
    {
        self::$resources[$model] = $resource;
    }

    public static function ability(string $action, Model|string|null $record = null): string
    {
        $model = $record instanceof Model ? $record::class : ($record ?? Ticket::class);

        return (self::$resources[$model] ?? TicketResource::class).'.'.$action;
    }

    public static function allows(?Authenticatable $user, string $action, ?Model $record = null): bool
    {
        $ability = self::ability($action, $record);

        if (! Gate::has($ability)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return Gate::forUser($user)->allows($ability, $record !== null ? [$record] : []);
    }

    public static function workflowAction(string $moveKey): string
    {
        return self::WORKFLOW_FAMILY.'.'.$moveKey;
    }
}
