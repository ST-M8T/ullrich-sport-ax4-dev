<?php

declare(strict_types=1);

namespace App\View\Composers\Fulfillment;

use Illuminate\View\View;

/**
 * Fulfillment Exports View Composer
 * Bereitet Exports-Index-Daten vor
 */
final class FulfillmentExportsComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        $formatSize = static function (?int $bytes): string {
            if ($bytes === null) {
                return '—';
            }

            if ($bytes >= 1_048_576) {
                return number_format($bytes / 1_048_576, 2, ',', '.').' MB';
            }

            if ($bytes >= 1024) {
                return number_format($bytes / 1024, 2, ',', '.').' KB';
            }

            return $bytes.' B';
        };

        $statusClasses = [
            'pending' => 'badge bg-secondary',
            'running' => 'badge bg-primary',
            'completed' => 'badge bg-success',
            'failed' => 'badge bg-danger',
        ];

        $filterValues = $data['filterValues'] ?? [];
        $values = $filterValues;

        $jobStatusOptions = [
            '' => 'Alle',
            'pending' => 'Ausstehend',
            'running' => 'Laufend',
            'completed' => 'Abgeschlossen',
            'failed' => 'Fehlgeschlagen',
        ];

        $view->with([
            'formatSize' => $formatSize,
            'statusClasses' => $statusClasses,
            'values' => $values,
            'jobStatusOptions' => $jobStatusOptions,
        ]);
    }
}
