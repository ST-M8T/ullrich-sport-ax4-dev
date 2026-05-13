<?php

declare(strict_types=1);

namespace App\Console\Commands\Dhl\Catalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogSuccessorMappingService;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use Illuminate\Console\Command;

/**
 * CLI adapter (Presentation, §7) listing every deprecated product with its
 * optional successor mapping. Read-only — no audit, no mutation.
 */
final class ListDeprecatedDhlCatalogCommand extends Command
{
    protected $signature = 'dhl:catalog:list-deprecated
        {--with-successor : Only list products that already have a replaced_by_code set.}
        {--without-successor : Only list products that have no replaced_by_code yet.}';

    protected $description = 'Lists all deprecated DHL catalog products and their successor mapping.';

    public function handle(
        DhlCatalogSuccessorMappingService $service,
        DhlProductRepository $repository,
    ): int {
        $withFlag = (bool) $this->option('with-successor');
        $withoutFlag = (bool) $this->option('without-successor');

        $rows = [];
        foreach ($service->listDeprecated() as $product) {
            $hasSuccessor = $product->replacedByCode() !== null;

            if ($withFlag && ! $withoutFlag && ! $hasSuccessor) {
                continue;
            }
            if ($withoutFlag && ! $withFlag && $hasSuccessor) {
                continue;
            }

            $rows[] = $this->buildRow($product, $repository);
        }

        if ($rows === []) {
            $this->info('No deprecated DHL products found for the given filter.');

            return self::SUCCESS;
        }

        $this->table(
            ['Code', 'Name', 'Deprecated At', 'Replaced By', 'Replaced By Name'],
            $rows,
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0:string,1:string,2:string,3:string,4:string}
     */
    private function buildRow(DhlProduct $product, DhlProductRepository $repository): array
    {
        $successorCode = $product->replacedByCode();
        $successorName = '-';
        if ($successorCode !== null) {
            $successor = $repository->findByCode($successorCode);
            $successorName = $successor !== null ? $successor->name() : '(unknown)';
        }

        return [
            $product->code()->value,
            $product->name(),
            $product->deprecatedAt()?->format('Y-m-d H:i:s') ?? '-',
            $successorCode?->value ?? '-',
            $successorName,
        ];
    }
}
