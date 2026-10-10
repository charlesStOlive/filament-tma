<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** L'échéance souhaitée d'un ticket n'est plus demandée. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tma_tickets', 'due_on')) {
            Schema::table('tma_tickets', function (Blueprint $table) {
                $table->dropColumn('due_on');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->date('due_on')->nullable()->after('priority');
        });
    }
};
