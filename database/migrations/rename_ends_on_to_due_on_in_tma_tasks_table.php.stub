<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une tâche projet a une échéance prévue (`due_on`), pas une date de fin fixe : sa vraie fin est le jour où elle est
 * terminée (`done_at`). Son début, s'il n'a pas été saisi, est le jour de sa création.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tma_tasks', function (Blueprint $table) {
            $table->renameColumn('ends_on', 'due_on');
        });

        DB::table('tma_tasks')->whereNull('starts_on')->update(['starts_on' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('tma_tasks', function (Blueprint $table) {
            $table->renameColumn('due_on', 'ends_on');
        });
    }
};
