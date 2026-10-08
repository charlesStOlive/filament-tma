<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les tables du lot 1a : les intervenants (acteurs), les tickets, leur chronologie et l'historique des
 * transitions. Les identifiants globaux (ulid) sont les mêmes dans toutes les applications qui partagent
 * un ticket ; `instance_uid` désigne une installation du plugin.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Une personne qui agit sur un ticket, d'ici ou d'une autre application : son id là-bas, et une copie
        // de son nom et de son e-mail, pour que l'historique reste lisible si elle disparaît.
        Schema::create('tma_actors', function (Blueprint $table) {
            $table->id();
            $table->string('instance_uid', 26);
            $table->string('remote_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['instance_uid', 'remote_id']);
        });

        Schema::create('tma_tickets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('number');
            $table->string('source_instance_uid', 26)->index();
            $table->nullableMorphs('client');
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('priority')->default('normal');
            $table->string('state')->index();
            $table->string('handling_mode')->default('local')->index();
            $table->foreignId('requester_actor_id')->constrained('tma_actors');
            $table->foreignId('assignee_actor_id')->nullable()->constrained('tma_actors')->nullOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('tma_actors');
            $table->string('created_on_instance_uid', 26);
            $table->date('due_on')->nullable();
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
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['source_instance_uid', 'number']);
        });

        // La chronologie : commentaires, notes, réponses (un niveau), résumés d'intervention.
        Schema::create('tma_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('subject');
            $table->foreignId('parent_id')->nullable()->constrained('tma_entries')->cascadeOnDelete();
            $table->string('type');
            $table->longText('body');
            $table->foreignId('author_actor_id')->constrained('tma_actors');
            $table->string('origin_instance_uid', 26);
            $table->string('origin')->default('ui');
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('edited_at')->nullable();
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
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_state_transitions');
        Schema::dropIfExists('tma_entries');
        Schema::dropIfExists('tma_tickets');
        Schema::dropIfExists('tma_actors');
    }
};
