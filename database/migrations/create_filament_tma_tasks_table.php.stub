<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 2 : les tâches projet (CDC §6.4, §8). Créées dans le maître seulement, destinées à une application cliente ;
 * invisibles chez elle tant qu'elles sont en brouillon. `publish_at` programme leur publication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_tasks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('number')->unique();
            $table->string('target_instance_uid', 26)->index();
            $table->nullableMorphs('client');
            $table->string('project')->nullable()->index();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('state')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('assignee_actor_id')->nullable()->constrained('tma_actors')->nullOnDelete();
            $table->foreignId('created_by_actor_id')->nullable()->constrained('tma_actors')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->string('sync_status')->default('local');
            $table->unsignedInteger('sync_version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->unsignedInteger('time_total_minutes')->default(0);
            $table->json('time_by_month')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_tasks');
    }
};
