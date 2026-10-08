<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 1b : les saisies de temps, côté maître (CDC §6.6, §10). Une ligne par intervention, en minutes, avec sa date :
 * c'est elle qui ventile le temps par mois. `invoice_*` porte le verrou de facturation (CDC §10.3, lot 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_time_entries', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->morphs('subject');
            $table->foreignId('actor_id')->constrained('tma_actors');
            $table->date('worked_on');
            $table->unsignedInteger('minutes');
            $table->text('description')->nullable();
            $table->boolean('billable')->default(true);
            $table->string('origin')->default('ui');
            $table->nullableMorphs('invoice');
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_type', 'subject_id', 'worked_on']);
            $table->index('worked_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_time_entries');
    }
};
