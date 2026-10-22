<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La fourchette d'un projet (en jours, décimaux : 0,5) et ses participants, nommés par le maître ; le client les reçoit
 * avec les tâches du projet. Les destinataires de ses tâches s'y ajoutent à l'affichage (`Project::participants()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tma_projects', function (Blueprint $table) {
            $table->decimal('estimate_min_days', 6, 2)->nullable()->after('description');
            $table->decimal('estimate_max_days', 6, 2)->nullable()->after('estimate_min_days');
        });

        Schema::create('tma_project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('tma_projects')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('tma_actors')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_project_members');

        Schema::table('tma_projects', function (Blueprint $table) {
            $table->dropColumn(['estimate_min_days', 'estimate_max_days']);
        });
    }
};
