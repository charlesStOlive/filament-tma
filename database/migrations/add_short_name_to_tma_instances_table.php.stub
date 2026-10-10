<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Le nom court du client d'une application (« EDM ») : il forme « Équipe EDM », « Publier chez EDM »… */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tma_instances', function (Blueprint $table) {
            $table->string('short_name', 30)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('tma_instances', function (Blueprint $table) {
            $table->dropColumn('short_name');
        });
    }
};
