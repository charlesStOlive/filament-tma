<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les agents du centre de service dans les applications clientes : une personne de l'équipe du maître qui a aussi un
 * compte dans l'application cliente (même e-mail), et y agit pour le centre de service.
 *
 * - tma_agent_links (maître) : qui est relié à quelle application ;
 * - tma_agents (client) : ses utilisateurs reliés, avec le jeton personnel que le maître leur a émis (limité à cette
 *   application), par lequel ils agissent dans le maître en leur nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_agent_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('tma_instances')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->unique(['instance_id', 'user_id']);
        });

        Schema::create('tma_agents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('email');
            $table->string('name');
            $table->string('master_uid', 26);
            $table->string('master_user_id');
            $table->text('token');
            $table->timestamp('linked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tma_agents');
        Schema::dropIfExists('tma_agent_links');
    }
};
