<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les personnes et les applications. Un acteur est une personne qui agit, d'ici ou d'une autre application : son id
 * là-bas, et une copie de son nom et de son e-mail, pour que l'historique reste lisible si elle disparaît.
 * `instance_uid` désigne une installation du plugin. Côté maître, chaque application cliente est une instance, liée au
 * compte de service dont elle détient le jeton ; les membres du Support y sont reliés (`tma_agent_links`). Côté client,
 * un membre du Support relié garde son jeton du maître (`tma_agents`).
 */
return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('tma_instances', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 26)->unique();
            $table->string('name');
            $table->string('short_name', 30)->nullable();
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
        Schema::dropIfExists('tma_instances');
        Schema::dropIfExists('tma_actors');
    }
};
