<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une note est lue de tous, sauf quand son auteur la garde pour lui (`private`) : elle ne se montre alors qu'à lui,
 * des deux côtés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tma_entries', function (Blueprint $table) {
            $table->boolean('private')->default(false)->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('tma_entries', function (Blueprint $table) {
            $table->dropColumn('private');
        });
    }
};
