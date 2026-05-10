<?php

declare(strict_types=1);

namespace App\Support\UI;

use Illuminate\Http\Request;

/**
 * Filter Tabs Service
 * Bereitet Filter-Tab-Daten vor
 * SOLID: Single Responsibility - Nur Filter-Tab-URL-Logik
 * DDD: Application Service - Orchestriert Filter-Tab-Logik
 */
final class FilterTabsService
{
    public function __construct(
        private readonly Request $request
    ) {}

    /**
     * Bereitet Filter-Tab-Daten vor
     *
     * @param  array<string, string>  $tabs
     * @param  array<string, mixed>  $queryParams
     * @return array<string, mixed>
     */
    public function prepareFilterTabData(
        array $tabs,
        ?string $activeTab = null,
        ?string $baseUrl = null,
        array $queryParams = []
    ): array {
        $baseUrl = $baseUrl ?? $this->request->url();

        $processedTabs = [];
        foreach ($tabs as $value => $label) {
            // Array-Keys sind in PHP int|string. Empty-String ('') ist die Konvention
            // für „Alle"-Filter (siehe FulfillmentOrdersComposer).
            $tabQuery = array_merge($queryParams, ['filter' => $value, 'page' => 1]);
            if ($value === '' || $value === 0) {
                unset($tabQuery['filter']);
            }
            $url = $baseUrl.'?'.http_build_query($tabQuery);
            $isActive = ($activeTab ?? '') === (string) $value;

            $processedTabs[] = [
                'value' => $value,
                'label' => $label,
                'url' => $url,
                'isActive' => $isActive,
            ];
        }

        return [
            'processedTabs' => $processedTabs,
        ];
    }
}
