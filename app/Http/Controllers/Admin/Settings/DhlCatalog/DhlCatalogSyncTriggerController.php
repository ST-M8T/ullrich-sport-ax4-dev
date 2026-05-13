<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlCatalogSyncStatus;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlCatalogSyncStatusRepository;
use App\Infrastructure\Persistence\Dhl\Catalog\Eloquent\DhlCatalogAuditLogModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use App\Jobs\Fulfillment\Dhl\RunDhlCatalogSyncJob;
use DateTimeImmutable;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Triggers and reports on a manual DHL catalog sync run (PROJ-6 / t15b).
 *
 * Engineering-Handbuch §7 (Presentation: thin), §20 (Permission), §24
 * (Idempotenz). Verhindert Doppel-Trigger über das Sync-Status-Repository:
 * solange `last_attempt_at` < 60 s alt ist und (noch) kein neuer Erfolg
 * vorliegt, gilt der Sync als laufend.
 */
final class DhlCatalogSyncTriggerController
{
    /**
     * Cooldown-Fenster nach dem ein erneuter Trigger erlaubt ist
     * (Sekunden). Spiegelt die maximale erwartete Sync-Laufzeit.
     */
    public const RUNNING_WINDOW_SECONDS = 60;

    public function __construct(
        private readonly DhlCatalogSyncStatusRepository $syncStatusRepository,
        private readonly BusDispatcher $bus,
        private readonly AuthFactory $auth,
        private readonly Gate $gate,
    ) {}

    public function trigger(Request $request): RedirectResponse
    {
        if (! $this->gate->allows('dhl-catalog.sync')) {
            throw new AccessDeniedHttpException;
        }

        $status = $this->syncStatusRepository->get();
        $now = new DateTimeImmutable;

        if ($this->isRunning($status, $now)) {
            return redirect()
                ->route('admin.settings.dhl.catalog.index')
                ->with('error', 'Ein DHL-Katalog-Sync läuft bereits. Bitte warte, bis der laufende Vorgang abgeschlossen ist.')
                ->setStatusCode(Response::HTTP_CONFLICT);
        }

        $actor = $this->resolveActor();

        $this->bus->dispatch(new RunDhlCatalogSyncJob(actor: $actor));
        $this->recordTriggerAudit($actor, $now);

        return redirect()
            ->route('admin.settings.dhl.catalog.index')
            ->with('success', 'DHL-Katalog-Sync wurde gestartet. Status wird in Kürze aktualisiert.');
    }

    public function status(Request $request): JsonResponse
    {
        if (! $this->gate->allows('dhl-catalog.sync')) {
            throw new AccessDeniedHttpException;
        }

        $status = $this->syncStatusRepository->get();
        $now = new DateTimeImmutable;

        return new JsonResponse([
            'running' => $this->isRunning($status, $now),
            'last_attempt_at' => $status->lastAttemptAt?->format(DATE_ATOM),
            'last_success_at' => $status->lastSuccessAt?->format(DATE_ATOM),
            'last_error' => $status->lastError,
            'consecutive_failures' => $status->consecutiveFailures,
        ]);
    }

    private function isRunning(DhlCatalogSyncStatus $status, DateTimeImmutable $now): bool
    {
        if ($status->lastAttemptAt === null) {
            return false;
        }

        $age = $now->getTimestamp() - $status->lastAttemptAt->getTimestamp();
        if ($age < 0 || $age > self::RUNNING_WINDOW_SECONDS) {
            return false;
        }

        // Falls Erfolg nach (oder gleich) Attempt: nicht mehr laufend.
        if ($status->lastSuccessAt !== null
            && $status->lastSuccessAt >= $status->lastAttemptAt
        ) {
            return false;
        }

        return true;
    }

    private function resolveActor(): string
    {
        $user = $this->auth->guard()->user();

        if ($user instanceof UserModel) {
            $email = (string) ($user->email ?? '');
            if ($email !== '') {
                return 'user:' . $email;
            }
            $id = $user->getAuthIdentifier();
            if ($id !== null && $id !== '') {
                return 'user:' . (string) $id;
            }
        }

        return 'user:unknown';
    }

    private function recordTriggerAudit(string $actor, DateTimeImmutable $at): void
    {
        // Manuelles Trigger-Audit als technischer Marker. Domain-Mutationen
        // erfolgen erst im Job durch den Sync-Service — diese Zeile
        // dokumentiert nur, dass ein Mensch den Sync gestartet hat.
        $row = new DhlCatalogAuditLogModel;
        $row->entity_type = DhlCatalogAuditLogger::ENTITY_PRODUCT;
        $row->entity_key = '*';
        $row->action = DhlCatalogAuditLogger::ACTION_UPDATED;
        $row->actor = $actor;
        $row->diff = ['event' => 'manual_sync_triggered'];
        $row->created_at = $at;
        $row->save();
    }
}
