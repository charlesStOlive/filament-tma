<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Le « résumé d'intervention » n'est plus un type à part : c'est un message de la chronologie. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tma_entries')->where('type', 'summary')->update(['type' => 'comment']);
    }

    public function down(): void
    {
        // Rien à défaire : un message reste un message.
    }
};
