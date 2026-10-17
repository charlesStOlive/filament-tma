<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le bilan du matin, au choix de chacun comme le courriel et la cloche ; vide, le réglage de l'application
 * (`notifications.defaults`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tma_notification_preferences', function (Blueprint $table) {
            $table->boolean('digest')->nullable()->after('channels');
        });
    }

    public function down(): void
    {
        Schema::table('tma_notification_preferences', function (Blueprint $table) {
            $table->dropColumn('digest');
        });
    }
};
