<?php

namespace CharlesStOlive\FilamentTma\Support\Demo;

use CharlesStOlive\FilamentTma\Support\Dates;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Le socle des jeux d'exemples : raconter une histoire dans le passé (`at()`), puis revenir au présent.
 */
abstract class Scenario
{
    private Carbon $now;

    /** Celui dont l'heure compte pour raconter (son fuseau, Paris en général) : le Support, ou l'équipe du client. */
    protected ?Authenticatable $teller = null;

    public function __construct(protected Command $output)
    {
        $this->now = Carbon::now();
    }

    abstract public function run(): void;

    /**
     * Joue `$story` comme il y a `$daysAgo` jours, à `$time` (heure du narrateur, Paris en général), puis revient à la
     * date d'avant (on peut imbriquer). Ce qui s'écrit garde cette date ; l'autre application la reçoit telle quelle.
     */
    protected function at(int $daysAgo, string $time, Closure $story): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($this->now->copy()->setTimezone(Dates::timezone($this->teller))->subDays($daysAgo)->setTimeFromTimeString($time)->utc());

        try {
            return $story();
        } finally {
            Carbon::setTestNow($previous);
        }
    }

    /** Une date à venir, à l'heure du narrateur : `in(7, '09:00')`. */
    protected function in(int $days, string $time): Carbon
    {
        return $this->now->copy()->setTimezone(Dates::timezone($this->teller))->addDays($days)->setTimeFromTimeString($time)->utc();
    }

    protected function say(string $line): void
    {
        $this->output->line('  · '.$line);
    }

    protected static function html(string ...$paragraphs): string
    {
        return implode('', array_map(fn (string $p): string => '<p>'.e($p).'</p>', $paragraphs));
    }
}
