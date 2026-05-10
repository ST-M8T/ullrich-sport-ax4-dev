<?php

declare(strict_types=1);

namespace App\Support\UI;

use Illuminate\Support\Facades\Route;

/**
 * Layout Service
 * Bereitet Layout-Daten vor
 * SOLID: Single Responsibility - Nur Layout-Daten-Verarbeitung
 * DDD: Application Service - Orchestriert Layout-Logik
 */
final class LayoutService
{
    /**
     * Bereitet Dokument-Titel vor
     */
    public function prepareDocumentTitle(?string $pageTitle, string $baseTitle = 'Ullrich Sport - DHL CSV Generator'): string
    {
        return empty($pageTitle) ? $baseTitle : "{$pageTitle} – {$baseTitle}";
    }

    /**
     * Bereitet Layout-Klasse vor
     */
    public function prepareLayoutClass(bool $showSidebar): string
    {
        return 'admin-layout'.($showSidebar ? '' : ' admin-layout--solo');
    }

    /**
     * Bereitet Logout-URL vor
     */
    public function prepareLogoutUrl(?string $logoutUrl): string
    {
        return $logoutUrl ?? (Route::has('logout') ? route('logout') : url('/logout'));
    }

    /**
     * Bereitet Display-Name vor
     */
    public function prepareDisplayName(mixed $identityUser, mixed $authUser, ?string $currentUser = null): string
    {
        if ($currentUser !== null) {
            return $currentUser;
        }

        if (is_object($identityUser) && method_exists($identityUser, 'displayName')) {
            return (string) $identityUser->displayName();
        }

        if (is_object($authUser)) {
            return (string) ($authUser->name ?? $authUser->email ?? 'Unbekannt');
        }

        return 'Unbekannt';
    }

    /**
     * Bereitet UI-Config vor
     *
     * @return array<string, string>
     */
    public function prepareUiConfig(?string $appName, ?string $companyLogo, ?string $dhlLogo, ?string $tagline): array
    {
        return [
            'app_name' => $appName ?? 'Ullrich Sport - DHL CSV Generator',
            'company_logo' => $companyLogo ?? asset('images/ullrich_logo_neu.png'),
            'dhl_logo' => $dhlLogo ?? 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/18/DHL_Freight.svg/567px-DHL_Freight.svg.png',
            'tagline' => $tagline ?? 'Professionelle Auftragsverarbeitung für DHL Freight',
        ];
    }

    /**
     * Bereitet Breadcrumb-Items vor
     *
     * @param  array<int, mixed>|null  $breadcrumbs
     * @return array<int, array{label: string, url: string|null}>
     */
    public function prepareBreadcrumbItems(?array $breadcrumbs, ?string $pageTitle): array
    {
        $items = collect($breadcrumbs ?? [])
            ->map(function ($item) {
                if (is_string($item)) {
                    return ['label' => $item, 'url' => null];
                }

                if (is_array($item)) {
                    return [
                        'label' => $item['label'] ?? $item['title'] ?? '',
                        'url' => $item['url'] ?? $item['href'] ?? null,
                    ];
                }

                return null;
            })
            ->filter(fn ($item) => filled($item['label'] ?? null))
            ->values()
            ->all();

        if (empty($items) && ! empty($pageTitle)) {
            $items = [
                ['label' => $pageTitle, 'url' => null],
            ];
        }

        return $items;
    }
}
