<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\Catalog;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architecture-level guard: the catalog domain layer must NEVER import
 * Infrastructure, Application, Presentation or framework code.
 *
 * Locks Engineering Handbook §3-§8 (layer direction) statically — without it,
 * future refactors could silently pull Eloquent or Illuminate facades into the
 * domain.
 */
final class DhlCatalogDomainIsolationTest extends TestCase
{
    private const FORBIDDEN_PREFIXES = [
        'Illuminate\\',
        'Laravel\\',
        'Symfony\\',
        'App\\Infrastructure\\',
        'App\\Application\\',
        'App\\Http\\',
        'App\\Jobs\\',
        'App\\Console\\',
        'App\\Providers\\',
        'App\\Models\\',
        'App\\ViewHelpers\\',
        'App\\Support\\',
        'Eloquent',
    ];

    public function test_no_forbidden_imports(): void
    {
        $base = dirname(__DIR__, 7) . '/app/Domain/Fulfillment/Shipping/Dhl/Catalog';
        self::assertDirectoryExists($base, sprintf('Catalog domain folder missing at %s', $base));

        $violations = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $relative = substr($file->getPathname(), strlen($base) + 1);

            if (preg_match_all('/^\s*use\s+([^\s;]+)\s*;/m', $content, $matches) > 0) {
                foreach ($matches[1] as $import) {
                    foreach (self::FORBIDDEN_PREFIXES as $forbidden) {
                        if (str_starts_with($import, $forbidden)) {
                            $violations[] = sprintf('%s imports %s', $relative, $import);
                        }
                    }
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "Catalog domain layer leaked outwards:\n" . implode("\n", $violations),
        );
    }
}
