<?php

declare(strict_types=1);

namespace App\Console\Commands\Dhl\Catalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogSuccessorMappingService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\DhlCatalogException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use Illuminate\Console\Command;
use Throwable;

/**
 * CLI adapter (Presentation, §7) for clearing the successor mapping
 * (`replaced_by_code = null`) of a DHL catalog product.
 */
final class UnsetDhlCatalogSuccessorCommand extends Command
{
    protected $signature = 'dhl:catalog:unset-successor
        {oldCode : Code of the product whose successor mapping is being cleared}
        {--actor= : Audit actor email (PFLICHT). Translated to "user:<email>".}';

    protected $description = 'Clears replaced_by_code for a DHL product (removes manual successor mapping).';

    public function handle(DhlCatalogSuccessorMappingService $service): int
    {
        $actor = $this->resolveActor();
        if ($actor === null) {
            return self::FAILURE;
        }

        try {
            $oldCode = DhlProductCode::fromString((string) $this->argument('oldCode'));
        } catch (DhlValueObjectException $e) {
            $this->error('Invalid product code: ' . $e->getMessage());

            return self::FAILURE;
        }

        try {
            $service->unsetSuccessor($oldCode, $actor);
        } catch (DhlCatalogException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Unset-successor aborted: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Successor for %s cleared (actor=%s)',
            $oldCode->value,
            $actor->value,
        ));

        return self::SUCCESS;
    }

    private function resolveActor(): ?AuditActor
    {
        $raw = $this->option('actor');
        if (! is_string($raw) || trim($raw) === '') {
            $this->error('--actor is required. Pass an email, e.g. --actor=admin@example.com.');

            return null;
        }
        $email = trim($raw);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('--actor must be a valid email address.');

            return null;
        }

        try {
            return new AuditActor('user:' . $email);
        } catch (DhlValueObjectException $e) {
            $this->error('Invalid actor: ' . $e->getMessage());

            return null;
        }
    }
}
