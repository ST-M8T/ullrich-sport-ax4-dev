<?php

declare(strict_types=1);

namespace App\View\Composers\Dispatch;

use Illuminate\View\View;

/**
 * Dispatch Lists Modal View Composer
 * Bereitet Modal-Daten für Dispatch-Lists vor
 */
final class DispatchListsModalComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['lists'])) {
            return;
        }

        $lists = $data['lists'];
        $filters = $data['filters'] ?? [];
        $perPage = $data['perPage'] ?? 25;

        $processedModals = [];
        foreach ($lists as $list) {
            $listId = $list->id()->toInt();
            $metrics = $list->metrics();

            $processedModals[] = [
                'list' => $list,
                'listId' => $listId,
                'metrics' => $metrics,
                'metricsModalId' => 'dispatch-metrics-'.$listId,
                'closeModalId' => 'dispatch-close-'.$listId,
                'exportModalId' => 'dispatch-export-'.$listId,
            ];
        }

        $view->with([
            'processedModals' => $processedModals,
            'filters' => $filters,
            'perPage' => $perPage,
        ]);
    }
}
