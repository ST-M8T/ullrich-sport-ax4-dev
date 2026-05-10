<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class GatewayLayerTest extends TestCase
{
    public function test_gateways_do_not_depend_on_domain_models_or_repositories(): void
    {
        foreach ($this->gatewayFiles() as $file) {
            $content = file_get_contents($file);
            $this->assertIsString($content);

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Domain[s]?\\\\.*Repository[^;]*;/m',
                $content,
                $file.' darf keine Domain-Repositories importieren.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Models\\\\/m',
                $content,
                $file.' darf keine App\\Models importieren.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Domains?\\\\.*\\\\Models\\\\/m',
                $content,
                $file.' darf keine Domain-Modelle importieren.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function gatewayFiles(): array
    {
        $patterns = [
            dirname(__DIR__, 3).'/app/Infrastructure/Integrations/**/*.php',
            dirname(__DIR__, 3).'/app/Application/**/Integrations/**/*.php',
        ];

        $files = [];
        foreach ($patterns as $pattern) {
            $files = array_merge($files, glob($pattern, GLOB_BRACE));
        }

        return array_values(array_filter($files, 'is_file'));
    }
}
