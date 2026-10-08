<?php

namespace CharlesStOlive\FilamentTma\Http\Controllers\Api\Client;

use CharlesStOlive\FilamentTma\Models\AllowanceStatus;
use CharlesStOlive\FilamentTma\Support\Tma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** `POST allowances` : l'état des forfaits de cette application ; il remplace le précédent. */
class AllowanceController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'data' => ['present', 'array'],
            'data.*.allowance_ulid' => ['required', 'string', 'size:26'],
            'data.*.instances' => ['required', 'array'],
        ]);

        $statuses = collect($request->input('data'))->filter(fn (array $status): bool => in_array(Tma::instanceUid(), $status['instances'], true));

        DB::transaction(function () use ($statuses): void {
            AllowanceStatus::query()->whereNotIn('allowance_ulid', $statuses->pluck('allowance_ulid'))->delete();

            foreach ($statuses as $status) {
                AllowanceStatus::query()->updateOrCreate(['allowance_ulid' => $status['allowance_ulid']], ['status' => $status, 'received_at' => now()]);
            }
        });

        return response()->json(['count' => $statuses->count()]);
    }
}
