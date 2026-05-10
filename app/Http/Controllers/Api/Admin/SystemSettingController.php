<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Configuration\Queries\ListSystemSettings;
use App\Application\Configuration\SystemSettingService;
use App\Domain\Configuration\SystemSetting;
use App\Http\Controllers\Api\Admin\Concerns\InteractsWithJsonApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SystemSettingController
{
    use InteractsWithJsonApiResponses;

    private const VALUE_TYPES = [
        'string',
        'int',
        'bool',
        'float',
        'json',
        'text',
        'secret',
    ];

    public function __construct(
        private readonly ListSystemSettings $listSettings,
        private readonly SystemSettingService $settings,
    ) {}

    public function index(): JsonResponse
    {
        $items = ($this->listSettings)();

        return $this->jsonApiResponse([
            'data' => array_map(
                fn (SystemSetting $setting): array => $this->transformSetting($setting),
                $items
            ),
            'meta' => [
                'count' => count($items),
            ],
        ]);
    }

    public function show(string $settingKey): JsonResponse
    {
        $setting = $this->findSetting($settingKey);
        if (! $setting) {
            return $this->jsonApiError(404, 'Not Found', sprintf('System setting "%s" was not found.', $settingKey));
        }

        return $this->jsonApiResponse([
            'data' => $this->transformSetting($setting),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data.type' => ['required', 'string', 'in:system-settings'],
            'data.attributes.key' => ['required', 'string', 'max:255'],
            'data.attributes.value' => ['nullable', 'string'],
            'data.attributes.value_type' => ['required', 'string', Rule::in(self::VALUE_TYPES)],
        ]);

        if ($validator->fails()) {
            return $this->jsonApiValidationErrors($validator->errors());
        }

        $attributes = Arr::get($validator->validated(), 'data.attributes', []);
        $key = trim((string) ($attributes['key'] ?? ''));
        if ($key === '') {
            return $this->jsonApiError(422, 'Unprocessable Entity', 'Setting key must not be empty.');
        }

        if ($this->findSetting($key)) {
            return $this->jsonApiError(409, 'Conflict', sprintf('System setting "%s" already exists.', $key));
        }

        $this->settings->set(
            $key,
            Arr::get($attributes, 'value'),
            (string) Arr::get($attributes, 'value_type', 'string'),
            $this->resolveUserId()
        );

        // Defensiv: Nach `set()` kann das Setting theoretisch nicht persistiert sein
        // (Race Condition oder externe DB-Probleme). PhpStan-Inferenz erkennt das nicht;
        // daher prüfen wir per Reflection mit nicht-narrowable Cast.
        /** @var SystemSetting|null $setting */
        $setting = $this->findSetting($key);

        if ($setting === null) {
            return $this->jsonApiError(500, 'Server Error', 'The setting could not be loaded after creation.');
        }

        return $this->jsonApiResponse([
            'data' => $this->transformSetting($setting),
        ], 201);
    }

    public function update(string $settingKey, Request $request): JsonResponse
    {
        $setting = $this->findSetting($settingKey);
        if (! $setting) {
            return $this->jsonApiError(404, 'Not Found', sprintf('System setting "%s" was not found.', $settingKey));
        }

        $validator = Validator::make($request->all(), [
            'data.type' => ['required', 'string', 'in:system-settings'],
            'data.id' => ['nullable', 'string', Rule::in([$setting->key()])],
            'data.attributes.value' => ['nullable', 'string'],
            'data.attributes.value_type' => ['required', 'string', Rule::in(self::VALUE_TYPES)],
        ]);

        if ($validator->fails()) {
            return $this->jsonApiValidationErrors($validator->errors());
        }

        $attributes = Arr::get($validator->validated(), 'data.attributes', []);

        $this->settings->set(
            $setting->key(),
            Arr::get($attributes, 'value'),
            (string) Arr::get($attributes, 'value_type', $setting->valueType()),
            $this->resolveUserId()
        );

        $updated = $this->findSetting($setting->key());
        if (! $updated) {
            return $this->jsonApiError(500, 'Server Error', 'The setting could not be loaded after update.');
        }

        return $this->jsonApiResponse([
            'data' => $this->transformSetting($updated),
        ]);
    }

    public function destroy(string $settingKey): JsonResponse|Response
    {
        $setting = $this->findSetting($settingKey);
        if (! $setting) {
            return $this->jsonApiError(404, 'Not Found', sprintf('System setting "%s" was not found.', $settingKey));
        }

        $this->settings->remove($setting->key());

        return response()->noContent()->header('Content-Type', 'application/vnd.api+json');
    }

    /**
     * @return array<string, mixed>
     */
    private function transformSetting(SystemSetting $setting): array
    {
        return [
            'type' => 'system-settings',
            'id' => $setting->key(),
            'attributes' => [
                'key' => $setting->key(),
                'value' => $setting->rawValue(),
                'value_type' => $setting->valueType(),
                'updated_by_user_id' => $setting->updatedByUserId(),
                'updated_at' => $this->formatDate($setting->updatedAt()),
                'is_configured' => $setting->rawValue() !== null && $setting->rawValue() !== '',
            ],
        ];
    }

    private function findSetting(string $settingKey): ?SystemSetting
    {
        $needle = trim($settingKey);
        foreach (($this->listSettings)() as $setting) {
            if (strcasecmp($setting->key(), $needle) === 0) {
                return $setting;
            }
        }

        return null;
    }

    private function resolveUserId(): ?int
    {
        $user = Auth::user();
        $id = $user?->getAuthIdentifier();

        return is_int($id) ? $id : null;
    }
}
