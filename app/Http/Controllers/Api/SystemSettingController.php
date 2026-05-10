<?php

namespace App\Http\Controllers\Api;

use App\Application\Configuration\SystemSettingService;
use Illuminate\Http\JsonResponse;

final class SystemSettingController
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function show(string $key): JsonResponse
    {
        $value = $this->settings->get($key);
        if ($value === null) {
            return response()->json(['message' => 'Setting not found'], 404);
        }

        return response()->json([
            'key' => $key,
            'value' => $value,
        ]);
    }
}
