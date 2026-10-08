<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lot 2 : un brouillon de ticket créé par le maître pour un client peut avoir une publication programmée. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->index()->after('due_on');
        });
    }

    public function down(): void
    {
        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->dropColumn('publish_at');
        });
    }
};
