<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Les projets : un titre, une application destinataire, N tâches. Le libellé libre « Projet » d'une tâche devient un
 * vrai projet (un par application et par libellé). Avec eux :
 * - un ticket peut venir d'une tâche (`tma_tickets.task_id`) ;
 * - une saisie de temps peut porter sur un message ou une note (`tma_time_entries.entry_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tma_projects', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('target_instance_uid', 26)->index();
            $table->nullableMorphs('client');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('tma_tasks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('client_id')->constrained('tma_projects')->nullOnDelete();
        });

        DB::table('tma_tasks')->whereNotNull('project')->where('project', '!=', '')
            ->select(['target_instance_uid', 'client_type', 'client_id', 'project'])
            ->distinct()
            ->get()
            ->each(function (object $row): void {
                $now = now();
                $projectId = DB::table('tma_projects')->insertGetId([
                    'ulid' => (string) Str::ulid(),
                    'target_instance_uid' => $row->target_instance_uid,
                    'client_type' => $row->client_type,
                    'client_id' => $row->client_id,
                    'title' => $row->project,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('tma_tasks')
                    ->where('target_instance_uid', $row->target_instance_uid)
                    ->where('project', $row->project)
                    ->update(['project_id' => $projectId]);
            });

        Schema::table('tma_tasks', function (Blueprint $table) {
            $table->dropIndex(['project']);
            $table->dropColumn('project');
        });

        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('client_id')->constrained('tma_tasks')->nullOnDelete();
        });

        Schema::table('tma_time_entries', function (Blueprint $table) {
            $table->foreignId('entry_id')->nullable()->after('subject_id')->constrained('tma_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tma_time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entry_id');
        });

        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
        });

        Schema::table('tma_tasks', function (Blueprint $table) {
            $table->string('project')->nullable()->index()->after('client_id');
        });

        DB::table('tma_tasks')->whereNotNull('project_id')->get(['id', 'project_id'])->each(function (object $task): void {
            DB::table('tma_tasks')->where('id', $task->id)
                ->update(['project' => DB::table('tma_projects')->where('id', $task->project_id)->value('title')]);
        });

        Schema::table('tma_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::dropIfExists('tma_projects');
    }
};
