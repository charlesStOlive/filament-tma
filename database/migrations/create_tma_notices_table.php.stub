<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les notifications du module, ici :
 * - `tma_notices` : ce qui a été dit à chacun (un passage, un message, une note, une relance…), envoyé tout de suite
 *   (`sent_at` posé) ou mis de côté pour le prochain récapitulatif (`sent_at` vide). Une même chose n'est dite qu'une
 *   fois à une personne (`key`) ;
 * - `tma_notification_preferences` : comment chacun veut être prévenu quand il est destinataire principal (courriel,
 *   cloche de l'application, ou les deux).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_notices', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 64);
            $table->string('event', 32);
            $table->string('key', 191);
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->string('mode', 16);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'sent_at']);
        });

        Schema::create('tma_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 64)->unique();
            $table->json('channels')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_notification_preferences');
        Schema::dropIfExists('tma_notices');
    }
};
