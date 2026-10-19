<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tma_notification_preferences')) {
            return;
        }

        if (Schema::hasTable('user_options')) {
            DB::transaction(function (): void {
                DB::table('tma_notification_preferences')->orderBy('id')->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        if ($row->channels === null || ! ctype_digit((string) $row->user_id) || ! DB::table('users')->where('id', $row->user_id)->exists()) {
                            continue;
                        }

                        $channels = json_decode($row->channels ?? '[]', true, 512, JSON_THROW_ON_ERROR);

                        if (! is_array($channels) || ! array_is_list($channels)) {
                            throw new RuntimeException('Invalid TMA notification channels for user '.$row->user_id);
                        }

                        if (DB::table('user_options')->where('user_id', $row->user_id)->where('name', 'tma')->exists()) {
                            continue;
                        }

                        DB::table('user_options')->insert([
                            'user_id' => $row->user_id,
                            'name' => 'tma',
                            'data' => json_encode(['notifications' => ['channels' => $channels]], JSON_THROW_ON_ERROR),
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]);
                    }
                });
            });
        }

        Schema::drop('tma_notification_preferences');
    }

    public function down(): void
    {
        if (Schema::hasTable('tma_notification_preferences')) {
            return;
        }

        Schema::create('tma_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->string('user_id', 64)->unique();
            $table->json('channels')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('user_options')) {
            DB::table('user_options')->where('name', 'tma')->orderBy('id')->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $data = json_decode($row->data ?? '{}', true, 512, JSON_THROW_ON_ERROR);

                    if (! array_key_exists('channels', $data['notifications'] ?? [])) {
                        continue;
                    }

                    DB::table('tma_notification_preferences')->insert([
                        'user_id' => (string) $row->user_id,
                        'channels' => json_encode($data['notifications']['channels'], JSON_THROW_ON_ERROR),
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });
        }
    }
};
