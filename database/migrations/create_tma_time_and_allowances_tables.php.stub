<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le temps passé (dans le maître seulement) et les forfaits. Une saisie porte sur un message ou une note de la fiche
 * (`entry_id`), ou sur la fiche entière ; son lien de facturation la verrouille. Côté client, les forfaits ne sont
 * qu'un état reçu du maître (`tma_allowance_statuses`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_time_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('subject');
            $table->foreignId('entry_id')->nullable()->constrained('tma_entries')->nullOnDelete();
            $table->foreignId('actor_id')->constrained('tma_actors');
            $table->date('worked_on');
            $table->unsignedInteger('minutes');
            $table->boolean('billable')->default(true);
            $table->string('origin')->default('ui');
            $table->nullableMorphs('invoice');
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_type', 'subject_id', 'worked_on']);
            $table->index('worked_on');
        });

        Schema::create('tma_allowances', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->nullableMorphs('client');
            $table->string('instance_uid', 26)->nullable()->index();
            $table->string('contract')->nullable();
            $table->string('period')->default('monthly');
            $table->unsignedInteger('minutes');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('tma_allowance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allowance_id')->constrained('tma_allowances')->cascadeOnDelete();
            $table->string('period_key');
            $table->unsignedSmallInteger('threshold');
            $table->unsignedInteger('consumed_minutes');
            $table->timestamp('reached_at');

            $table->unique(['allowance_id', 'period_key', 'threshold']);
        });

        Schema::create('tma_allowance_statuses', function (Blueprint $table) {
            $table->id();
            $table->ulid('allowance_ulid')->unique();
            $table->json('status');
            $table->timestamp('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_allowance_statuses');
        Schema::dropIfExists('tma_allowance_alerts');
        Schema::dropIfExists('tma_allowances');
        Schema::dropIfExists('tma_time_entries');
    }
};
