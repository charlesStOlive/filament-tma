<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 2 : les forfaits de TMA (CDC §11). Un volume par période (mois ou trimestre), pour un client (partagé entre
 * ses applications, `instance_uid` vide) ou pour une application. `tma_allowance_alerts` retient chaque seuil
 * franchi, une fois par période. Côté client, `tma_allowance_statuses` garde l'état envoyé par le maître.
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }
};
