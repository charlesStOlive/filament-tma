<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 1b : les applications clientes connues du maître, et le suivi de la synchronisation des tickets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_instances', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 26)->unique();
            $table->string('name');
            $table->string('url');
            $table->nullableMorphs('client');
            // L'utilisateur d'ici dont l'application cliente détient un jeton : c'est par lui qu'on la reconnaît.
            $table->unsignedBigInteger('api_user_id')->nullable()->unique();
            // Le jeton émis par l'application cliente pour que le maître l'appelle (chiffré).
            $table->text('token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->text('last_sync_error')->nullable()->after('synced_at');
            // Côté client : les totaux de temps reçus du maître (les lignes de temps restent dans le maître).
            $table->unsignedInteger('time_total_minutes')->default(0)->after('last_sync_error');
            $table->json('time_by_month')->nullable()->after('time_total_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->dropColumn(['last_sync_error', 'time_total_minutes', 'time_by_month']);
        });

        Schema::dropIfExists('tma_instances');
    }
};
