<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class DomainLayerTest extends TestCase
{
    public function test_domain_layer_has_no_framework_dependencies(): void
    {
        foreach ($this->domainFiles() as $file) {
            $content = file_get_contents($file);
            $this->assertIsString($content);

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+Illuminate\\\\/m',
                $content,
                $file.' darf keine Illuminate-Abhängigkeiten im Domain-Layer importieren.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Models\\\\/m',
                $content,
                $file.' darf keine App\\Models im Domain-Layer importieren.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Infrastructure\\\\/m',
                $content,
                $file.' darf keine Infrastructure-Klassen im Domain-Layer importieren.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+Illuminate\\\\Support\\\\Facades\\\\DB;/m',
                $content,
                $file.' darf keine DB-Facade im Domain-Layer importieren.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function domainFiles(): array
    {
        $patterns = [
            dirname(__DIR__, 3).'/app/Domain/**/*.php',
            dirname(__DIR__, 3).'/app/Domains/**/*.php',
        ];

        $files = [];
        foreach ($patterns as $pattern) {
            $files = array_merge($files, glob($pattern, GLOB_BRACE));
        }

        return array_values(array_filter($files, 'is_file'));
    }
}
