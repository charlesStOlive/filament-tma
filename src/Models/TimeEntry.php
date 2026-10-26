<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Enums\Origin;
use CharlesStOlive\FilamentTma\Events\TimeEntryChanged;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Une saisie de temps (maître seulement) : qui, quel jour, combien de minutes, facturable ou non, et sur quel message
 * ou quelle note de la fiche (`entry_id`, sinon la fiche entière). Ce qui a été fait se lit dans ce message.
 *
 * Verrou de facturation (CDC §10.3) : une saisie rattachée à une facture ne se modifie ni ne se supprime, quel que
 * soit l'état de la facture. Il faut d'abord défaire le rattachement (lot 3). Le verrou est dans le modèle : il vaut
 * pour l'interface, l'API et les commandes.
 *
 * @property Carbon $worked_on
 * @property int $minutes
 * @property bool $billable
 */
class TimeEntry extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'tma_time_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'worked_on' => 'date',
            'minutes' => 'integer',
            'billable' => 'boolean',
            'origin' => Origin::class,
            'invoiced_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (! Tma::isMaster()) {
                throw new LogicException('Le temps ne se saisit que dans l’application maître.');
            }
        });

        static::updating(function (TimeEntry $entry): void {
            // Seul le rattachement lui-même peut changer (lot 3 : le défaire).
            $onlyInvoiceLink = array_diff(array_keys($entry->getDirty()), ['invoice_type', 'invoice_id', 'invoiced_at', 'updated_at']) === [];

            if ($entry->getOriginal('invoice_id') !== null && ! $onlyInvoiceLink) {
                throw new LogicException(__('filament-tma::time.errors.invoiced_no_edit'));
            }
        });

        static::deleting(function (TimeEntry $entry): void {
            if ($entry->isInvoiced()) {
                throw new LogicException(__('filament-tma::time.errors.invoiced_no_delete'));
            }
        });

        // Quelle que soit l'origine (interface, commande, API), le changement se sait : audit, envoi des totaux.
        static::created(fn (TimeEntry $entry) => event(new TimeEntryChanged($entry, 'created')));
        static::updated(fn (TimeEntry $entry) => event(new TimeEntryChanged($entry, 'updated')));
        static::deleted(fn (TimeEntry $entry) => event(new TimeEntryChanged($entry, 'deleted')));
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Le message ou la note sur lequel porte ce temps, s'il en a un. */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'entry_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_id');
    }

    public function invoice(): MorphTo
    {
        return $this->morphTo();
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }

    /** Le mois d'imputation : celui de la date de réalisation (CDC §10.2). */
    public function month(): string
    {
        return $this->worked_on->format('Y-m');
    }
}
