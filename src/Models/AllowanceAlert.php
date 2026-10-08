<?php

namespace CharlesStOlive\FilamentTma\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un seuil franchi par un forfait sur une période : il ne se déclenche qu'une fois (CDC §11.3). */
class AllowanceAlert extends Model
{
    public $timestamps = false;

    protected $table = 'tma_allowance_alerts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['reached_at' => 'datetime', 'threshold' => 'integer', 'consumed_minutes' => 'integer'];
    }

    public function allowance(): BelongsTo
    {
        return $this->belongsTo(Allowance::class);
    }
}
