<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une saisie de temps ne dit plus elle-même ce qui a été fait : elle porte sur un message ou une note de la fiche
 * (`entry_id`), écrit avec elle au besoin, ou sur la fiche entière.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tma_time_entries', 'description')) {
            Schema::table('tma_time_entries', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tma_time_entries', function (Blueprint $table) {
            $table->text('description')->nullable()->after('minutes');
        });
    }
};
