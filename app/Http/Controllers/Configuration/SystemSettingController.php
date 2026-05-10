<?php

namespace App\Http\Controllers\Configuration;

use App\Application\Configuration\Queries\ListMailTemplates;
use App\Application\Configuration\Queries\ListNotifications;
use App\Application\Configuration\Queries\ListSystemSettings;
use App\Application\Configuration\SystemSettingService;
use App\Application\Fulfillment\Masterdata\Queries\GetFulfillmentMasterdataCatalog;
use App\Application\Identity\Authorization\RoleManager;
use App\Application\Identity\Queries\SearchUsers;
use App\Application\Monitoring\SystemStatusService;
use App\Domain\Configuration\SystemSetting;
use App\Support\Security\SecurityContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

final class SystemSettingController
{
    private const VALUE_TYPES = [
        'string',
        'int',
        'bool',
        'float',
        'json',
        'text',
        'secret',
    ];

    /**
     * @var array<string,mixed>
     */
    private array $groups;

    /**
     * @var array<int,string>
     */
    private array $managedSettingKeys = [];

    public function __construct(
        private readonly ListSystemSettings $listSettings,
        private readonly SystemSettingService $settingService,
        private readonly Redirector $redirector,
        private readonly SystemStatusService $systemStatus,
        private readonly GetFulfillmentMasterdataCatalog $getMasterdataCatalog,
        private readonly ListMailTemplates $listMailTemplates,
        private readonly ListNotifications $listNotifications,
        private readonly SearchUsers $searchUsers,
        private readonly RoleManager $roleManager,
    ) {
        $this->groups = (array) config('system-settings.groups', []);
        $this->managedSettingKeys = collect($this->groups)
            ->flatMap(fn ($group) => array_map(
                fn (array $field) => $field['key'],
                $group['fields'] ?? []
            ))
            ->all();
    }

    public function index(): View
    {
        $allSettings = collect(($this->listSettings)());
        $settingsByKey = $allSettings->keyBy(fn (SystemSetting $setting) => $setting->key());

        $otherSettings = $allSettings->filter(
            fn (SystemSetting $setting) => ! in_array($setting->key(), $this->managedSettingKeys, true)
        );
        $masterdataCatalog = ($this->getMasterdataCatalog)();
        $mailTemplates = ($this->listMailTemplates)();
        $notifications = ($this->listNotifications)([], 100, 0);
        $users = ($this->searchUsers)([]);
        $roleOptions = $this->roleManager->selectableRoles();

        return view('configuration.settings.index', [
            'settings' => $settingsByKey,
            'settingsTable' => $allSettings,
            'valueTypes' => self::VALUE_TYPES,
            'groups' => $this->groups,
            'otherSettings' => $otherSettings,
            'systemStatus' => $this->systemStatus->status(),
            'catalog' => $masterdataCatalog,
            'mailTemplates' => $mailTemplates,
            'notifications' => $notifications,
            'users' => $users,
            'roleOptions' => $roleOptions,
        ]);
    }

    public function create(): View
    {
        return view('configuration.settings.create', [
            'valueTypes' => self::VALUE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'setting_key' => ['required', 'string', 'max:255'],
            'setting_value' => ['nullable', 'string'],
            'value_type' => ['required', 'string', 'max:32'],
        ]);

        $key = trim($data['setting_key']);

        if ($this->findSetting($key)) {
            return $this->redirector
                ->route('configuration-settings.edit', ['settingKey' => $key])
                ->with('error', sprintf('Einstellung "%s" existiert bereits.', $key));
        }

        $this->settingService->set(
            $key,
            $data['setting_value'] ?? null,
            $data['value_type'],
            ((int) Auth::id()) ?: null,
            SecurityContext::fromRequest($request)
        );

        return $this->redirector
            ->route('configuration-settings')
            ->with('success', sprintf('Einstellung "%s" wurde erstellt.', $key));
    }

    public function edit(string $settingKey): View|RedirectResponse
    {
        $key = trim($settingKey);
        $setting = $this->findSetting($key);

        if (! $setting) {
            return $this->redirector
                ->route('configuration-settings')
                ->with('error', sprintf('Einstellung "%s" wurde nicht gefunden.', $key));
        }

        return view('configuration.settings.edit', [
            'setting' => $setting,
            'valueTypes' => self::VALUE_TYPES,
        ]);
    }

    public function update(string $settingKey, Request $request): RedirectResponse
    {
        $key = trim($settingKey);
        $setting = $this->findSetting($key);

        if (! $setting) {
            return $this->redirector
                ->route('configuration-settings')
                ->with('error', sprintf('Einstellung "%s" wurde nicht gefunden.', $key));
        }

        $data = $request->validate([
            'setting_value' => ['nullable', 'string'],
            'value_type' => ['required', 'string', 'max:32'],
        ]);

        $this->settingService->set(
            $key,
            $data['setting_value'] ?? null,
            $data['value_type'],
            ((int) Auth::id()) ?: null,
            SecurityContext::fromRequest($request)
        );

        return $this->redirector
            ->route('configuration-settings')
            ->with('success', sprintf('Einstellung "%s" wurde aktualisiert.', $key));
    }

    public function updateGroup(string $group, Request $request): RedirectResponse
    {
        $schema = $this->groups[$group] ?? null;

        if (! $schema) {
            abort(404);
        }

        $fields = $schema['fields'] ?? [];
        $rules = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $type = $field['type'] ?? 'text';

            $rule = match ($type) {
                'number' => ['nullable', 'numeric'],
                'checkbox' => ['nullable', 'boolean'],
                'email' => ['nullable', 'email'],
                'select' => ['nullable', 'string', 'max:255'],
                'password', 'text', 'textarea' => ['nullable', 'string'],
                default => ['nullable', 'string'],
            };

            $rules[$key] = $rule;
        }

        $validated = $request->validate($rules);

        foreach ($fields as $field) {
            $key = $field['key'];
            $valueType = $field['value_type'] ?? 'string';
            $type = $field['type'] ?? 'text';

            $value = $this->extractFieldValue($request, $field, $validated);

            if (($field['skip_if_empty'] ?? false) && ($value === null || $value === '')) {
                continue;
            }

            $this->settingService->set(
                $key,
                $value,
                $valueType,
                ((int) Auth::id()) ?: null,
                SecurityContext::fromRequest($request)
            );
        }

        return $this->redirector
            ->route('configuration-settings')
            ->with('success', sprintf('%s gespeichert.', $schema['label'] ?? 'Einstellungen'));
    }

    /**
     * @param  array<string,mixed>  $field
     * @param  array<string,mixed>  $validated
     */
    private function extractFieldValue(Request $request, array $field, array $validated): ?string
    {
        $key = $field['key'];
        $type = $field['type'] ?? 'text';

        return match ($type) {
            // isset() prüft bereits auf null — kein zusätzliches `!== null` nötig.
            'number' => isset($validated[$key])
                ? (string) $validated[$key]
                : null,
            'checkbox' => $request->boolean($key) ? '1' : '0',
            'textarea', 'email', 'text', 'select' => $validated[$key] ?? null,
            'password' => $validated[$key] ?? null,
            default => $validated[$key] ?? null,
        };
    }

    private function findSetting(string $key): ?SystemSetting
    {
        /** @var Collection<int,SystemSetting> $collection */
        $collection = collect(($this->listSettings)());

        return $collection->first(
            fn (SystemSetting $setting) => strcasecmp($setting->key(), $key) === 0
        );
    }
}
