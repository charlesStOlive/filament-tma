<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les tickets, et ce qu'ils partagent avec les tâches : la chronologie (messages, réponses, notes), l'historique des
 * passages et les destinataires. Les identifiants globaux (ulid) sont les mêmes dans toutes les applications qui
 * partagent un ticket. Un ticket va toujours d'une application cliente (`source_instance_uid`) au Support : le maître
 * fait autorité dès qu'il quitte le brouillon (addendum §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('number');
            $table->string('source_instance_uid', 26)->index();
            $table->nullableMorphs('client');
            // La tâche projet dont il part, s'il en vient une.
            $table->foreignId('task_id')->nullable()->constrained('tma_tasks')->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('priority')->default('normal');
            $table->string('state')->index();
            $table->foreignId('requester_actor_id')->constrained('tma_actors');
            $table->foreignId('assignee_actor_id')->nullable()->constrained('tma_actors')->nullOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('tma_actors');
            $table->string('created_on_instance_uid', 26);
            // Un brouillon préparé par le maître : sa publication programmée.
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->timestamp('waiting_since')->nullable();
            $table->timestamp('paused_until')->nullable();
            $table->timestamp('validation_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('auto_close_warned_at')->nullable();
            $table->string('sync_status')->default('local');
            $table->unsignedInteger('sync_version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            // Côté client : les totaux de temps reçus du maître (les lignes de temps restent dans le maître).
            $table->unsignedInteger('time_total_minutes')->default(0);
            $table->json('time_by_month')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_instance_uid', 'number']);
        });

        // La chronologie d'un ticket ou d'une tâche : messages, réponses (un niveau) et notes, numérotées par fiche.
        Schema::create('tma_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('subject');
            $table->foreignId('parent_id')->nullable()->constrained('tma_entries')->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('number')->nullable();
            $table->boolean('private')->default(false);
            $table->longText('body');
            $table->foreignId('author_actor_id')->constrained('tma_actors');
            $table->string('origin_instance_uid', 26);
            $table->string('origin')->default('ui');
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_type', 'subject_id', 'created_at']);
        });

        Schema::create('tma_state_transitions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('subject');
            $table->string('move');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->foreignId('actor_id')->nullable()->constrained('tma_actors');
            $table->string('origin_instance_uid', 26);
            $table->string('origin')->default('ui');
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id', 'created_at']);
        });

        // Les destinataires d'un ticket ou d'une tâche : principaux (prévenus tout de suite) ou secondaires (le bilan).
        Schema::create('tma_recipients', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('actor_id')->constrained('tma_actors')->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_recipients');
        Schema::dropIfExists('tma_state_transitions');
        Schema::dropIfExists('tma_entries');
        Schema::dropIfExists('tma_tickets');
    }
};
