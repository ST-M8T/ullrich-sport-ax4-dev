<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use App\Application\Fulfillment\Masterdata\Dto\FulfillmentMasterdataCatalog;
use Illuminate\View\View;

/**
 * Fulfillment Masterdata Catalog Partial View Composer
 * Bereitet Catalog-Partial-Daten vor
 */
final class FulfillmentMasterdataCatalogPartialComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $catalog = $data['catalog'] ?? null;
        if (! $catalog instanceof FulfillmentMasterdataCatalog) {
            return;
        }
        $masterdataTabParam = $data['masterdataTabParam'] ?? 'masterdata_tab';

        $packagingCount = $catalog->packagingProfilesCount();
        $assemblyCount = $catalog->assemblyOptionsCount();
        $variationCount = $catalog->variationProfilesCount();
        $senderCount = $catalog->senderProfilesCount();
        $senderRulesCount = $catalog->senderRulesCount();
        $freightCount = $catalog->freightProfilesCount();

        // Thematisch sortiert: Versand → Artikel
        $tabs = [
            'packaging' => ['label' => 'Verpackungen', 'count' => $packagingCount, 'category' => 'versand'],
            'freight' => ['label' => 'Versandprofile', 'count' => $freightCount, 'category' => 'versand'],
            'sender' => ['label' => 'Versender', 'count' => $senderCount, 'category' => 'versand'],
            'sender-rules' => ['label' => 'Versender-Regeln', 'count' => $senderRulesCount, 'category' => 'versand'],
            'variations' => ['label' => 'Varianten', 'count' => $variationCount, 'category' => 'artikel'],
            'assembly' => ['label' => 'Vormontage', 'count' => $assemblyCount, 'category' => 'artikel'],
        ];

        $activeTab = request()->query($masterdataTabParam, array_key_first($tabs));

        $masterdataTabs = collect($tabs)->mapWithKeys(function ($tab, $key) {
            return [$key => [
                'label' => $tab['label'],
                'badge' => $tab['count'],
            ]];
        })->all();

        $masterdataTabGroups = [
            'versand' => [
                'label' => 'Versand',
                'tabs' => $this->tabsForCategory($tabs, 'versand'),
            ],
            'artikel' => [
                'label' => 'Artikel',
                'tabs' => $this->tabsForCategory($tabs, 'artikel'),
            ],
        ];

        $view->with([
            'packagingCount' => $packagingCount,
            'assemblyCount' => $assemblyCount,
            'variationCount' => $variationCount,
            'senderCount' => $senderCount,
            'senderRulesCount' => $senderRulesCount,
            'freightCount' => $freightCount,
            'tabs' => $tabs,
            'masterdataTabs' => $masterdataTabs,
            'masterdataTabGroups' => array_filter($masterdataTabGroups, fn (array $group): bool => $group['tabs'] !== []),
            'activeTab' => $activeTab,
            'masterdataTabParam' => $masterdataTabParam,
        ]);
    }

    /**
     * @param  array<string,array{label:string,count:int,category:string}>  $tabs
     * @return array<string,string>
     */
    private function tabsForCategory(array $tabs, string $category): array
    {
        return collect($tabs)
            ->filter(fn (array $tab): bool => $tab['category'] === $category)
            ->mapWithKeys(fn (array $tab, string $key): array => [$key => $tab['label']])
            ->all();
    }
}
