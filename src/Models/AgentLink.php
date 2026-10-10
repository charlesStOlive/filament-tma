<?php

namespace CharlesStOlive\FilamentTma\Models;

use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maître : une personne de l'équipe reliée à une application cliente, où elle agit pour le centre de service. */
class AgentLink extends Model
{
    protected $table = 'tma_agent_links';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime'];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Tma::userModel(), 'user_id');
    }
}
