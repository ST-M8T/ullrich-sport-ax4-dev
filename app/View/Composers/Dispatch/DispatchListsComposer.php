<?php

declare(strict_types=1);

namespace App\View\Composers\Dispatch;

use Illuminate\View\View;

/**
 * Dispatch Lists View Composer
 * Bereitet Dispatch-Lists-Index-Daten vor
 */
final class DispatchListsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['pagination'])) {
            return;
        }

        $pagination = $data['pagination'];
        $filters = $data['filters'] ?? [];
        $lists = $data['lists'] ?? [];

        $statusOptions = [
            '' => 'Alle Stati',
            'open' => 'Offen',
            'closed' => 'Geschlossen',
            'exported' => 'Exportiert',
        ];

        $processedLists = [];
        foreach ($lists as $list) {
            $listId = $list->id()->toInt();
            $scanCount = $list->scanCount();
            $status = $list->status();
            $statusTone = match ($status) {
                'closed' => 'success',
                'exported' => 'info',
                default => 'warning',
            };
            $metricsModalId = 'dispatch-metrics-'.$listId;
            $closeModalId = 'dispatch-close-'.$listId;
            $exportModalId = 'dispatch-export-'.$listId;
            $closeDisabled = in_array($status, ['closed', 'exported'], true);
            $exportDisabled = $status !== 'closed';

            $processedLists[] = [
                'list' => $list,
                'listId' => $listId,
                'scanCount' => $scanCount,
                'status' => $status,
                'statusTone' => $statusTone,
                'metricsModalId' => $metricsModalId,
                'closeModalId' => $closeModalId,
                'exportModalId' => $exportModalId,
                'closeDisabled' => $closeDisabled,
                'exportDisabled' => $exportDisabled,
            ];
        }

        $view->with([
            'statusOptions' => $statusOptions,
            'filters' => $filters,
            'page' => $pagination->page,
            'perPage' => $pagination->perPage,
            'totalPages' => $pagination->totalPages(),
            'totalLists' => $pagination->total,
            'processedLists' => $processedLists,
        ]);
    }
}
