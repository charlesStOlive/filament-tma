<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les notes ne sont plus internes : tout le monde sur le ticket les lit, des deux côtés. Une note porte un numéro
 * (n° 1, 2… par ticket ou tâche), s'archive, et commente un message (son parent) ou le ticket entier (sans parent).
 * Le partage (`shared_at`) n'a plus d'objet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tma_entries', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('type');
            $table->timestamp('archived_at')->nullable()->after('edited_at');
        });

        // Une note qui répondait à une note porte désormais sur le ticket entier.
        DB::table('tma_entries')->where('type', 'note')->whereNotNull('parent_id')->update(['parent_id' => null]);

        $counters = [];

        DB::table('tma_entries')->where('type', 'note')->orderBy('created_at')->orderBy('id')
            ->select(['id', 'subject_type', 'subject_id'])
            ->each(function (object $note) use (&$counters): void {
                $key = $note->subject_type.':'.$note->subject_id;
                $counters[$key] = ($counters[$key] ?? 0) + 1;

                DB::table('tma_entries')->where('id', $note->id)->update(['number' => $counters[$key]]);
            });

        Schema::table('tma_entries', function (Blueprint $table) {
            $table->dropColumn('shared_at');
        });
    }

    public function down(): void
    {
        Schema::table('tma_entries', function (Blueprint $table) {
            $table->timestamp('shared_at')->nullable()->after('origin');
        });

        DB::table('tma_entries')->where('type', 'note')->update(['shared_at' => DB::raw('created_at')]);

        Schema::table('tma_entries', function (Blueprint $table) {
            $table->dropColumn(['number', 'archived_at']);
        });
    }
};
