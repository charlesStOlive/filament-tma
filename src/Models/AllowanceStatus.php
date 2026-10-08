<?php

namespace CharlesStOlive\FilamentTma\Models;

use Illuminate\Database\Eloquent\Model;

/** Côté client : l'état d'un forfait, tel que le maître l'a envoyé (`Support\AllowanceMeter::status()`). */
class AllowanceStatus extends Model
{
    public $timestamps = false;

    protected $table = 'tma_allowance_statuses';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => 'array', 'received_at' => 'datetime'];
    }
}
