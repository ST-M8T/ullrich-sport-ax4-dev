<?php

declare(strict_types=1);

namespace App\View\Composers\Shared;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Filter Form View Composer
 * Bereitet Filter-Form-Daten vor
 */
final class FilterFormComposer
{
    public function __construct(
        private readonly Request $request
    ) {}

    public function compose(View $view): void
    {
        $data = $view->getData();

        $resolvedAction = $data['action'] ?? $this->request->url();

        $view->with([
            'resolvedAction' => $resolvedAction,
        ]);
    }
}
