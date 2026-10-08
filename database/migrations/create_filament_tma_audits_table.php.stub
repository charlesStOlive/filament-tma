<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 3 : le journal d'audit (CDC §23) — qui, depuis quelle application, quand, quoi, sur quel objet, avant et
 * après, et d'où (interface, API, commande, planificateur). Les changements d'état ont leur propre historique
 * (`tma_state_transitions`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            $table->string('subject_label')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('tma_actors')->nullOnDelete();
            $table->string('instance_uid', 26);
            $table->string('origin');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_audits');
    }
};
