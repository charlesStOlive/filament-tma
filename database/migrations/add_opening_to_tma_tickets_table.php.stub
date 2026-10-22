<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comment un ticket s'est ouvert (`Enums\TicketOpening`) : une demande du client, le Support au nom d'un client, le
 * Support qui s'adresse au client, ou qui l'informe. Les tickets déjà là sont des demandes ; ceux préparés par le
 * Support, « au nom de ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->string('opening')->default('request')->after('priority');
        });

        DB::table('tma_tickets')->whereColumn('created_on_instance_uid', '!=', 'source_instance_uid')->update(['opening' => 'on_behalf']);
    }

    public function down(): void
    {
        Schema::table('tma_tickets', function (Blueprint $table) {
            $table->dropColumn('opening');
        });
    }
};
