<?php

use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les destinataires d'un ticket ou d'une tâche : principaux (prévenus tout de suite) ou secondaires (dans le bilan).
 * Les tickets et tâches existants gardent leurs personnes : auteur, demandeur et intervenant deviennent principaux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_recipients', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('actor_id')->constrained('tma_actors')->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'actor_id']);
        });

        $now = now();

        foreach ([[Ticket::class, 'tma_tickets', ['created_by_actor_id', 'requester_actor_id', 'assignee_actor_id']], [ProjectTask::class, 'tma_tasks', ['created_by_actor_id', 'assignee_actor_id']]] as [$model, $table, $columns]) {
            $morph = (new $model)->getMorphClass();

            DB::table($table)->select(['id', ...$columns])->orderBy('id')->each(function (object $row) use ($morph, $columns, $now): void {
                $actors = collect($columns)->map(fn (string $column): mixed => $row->{$column})->filter()->unique();

                DB::table('tma_recipients')->insertOrIgnore($actors->map(fn (mixed $actor): array => [
                    'subject_type' => $morph,
                    'subject_id' => $row->id,
                    'actor_id' => $actor,
                    'role' => 'primary',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_recipients');
    }
};
