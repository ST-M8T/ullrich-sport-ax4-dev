<?php

declare(strict_types=1);

namespace App\View\Composers\Shared;

use App\Support\UI\NavigationService;
use Illuminate\View\View;

/**
 * Navigation View Composer
 * Bereitet Navigation-Items für Views vor
 * SOLID: Single Responsibility - Nur Navigation-Daten vorbereiten
 * DDD: Presentation Layer - View-spezifische Daten
 */
final class NavigationComposer
{
    public function __construct(
        private readonly NavigationService $navigationService
    ) {}

    /**
     * Bindet Navigation-Daten an View
     */
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (array_key_exists('navigationItems', $data)) {
            return;
        }

        $currentSection = $data['currentSection'] ?? '';
        $items = $data['items'] ?? null;

        $navigationItems = $this->navigationService->prepareItems($items, $currentSection);

        $view->with('navigationItems', $navigationItems);
    }
}
