<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class ServiceLayerTest extends TestCase
{
    public function test_application_services_do_not_import_forbidden_dependencies(): void
    {
        foreach ($this->serviceFiles() as $file) {
            $content = file_get_contents($file);
            $this->assertIsString($content);

            // Keine Eloquent-Modelle oder App\Models im Service-Layer
            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Models\\\\/m',
                $content,
                $file.' darf keine App\\Models importieren.'
            );

            // Keine Repository-Implementierungen oder Infrastructure-Repos im Service-Layer
            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Domains?\\\\.*Repositories\\\\Eloquent\\\\/m',
                $content,
                $file.' darf keine Eloquent-Repository-Implementierungen importieren.'
            );

            // Keine direkten Infrastructure-Imports
            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Infrastructure\\\\/m',
                $content,
                $file.' darf keine Infrastructure-Klassen importieren.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function serviceFiles(): array
    {
        $patterns = [
            dirname(__DIR__, 3).'/app/Application/**/*.php',
            dirname(__DIR__, 3).'/app/Domains/*/Services/**/*.php',
            dirname(__DIR__, 3).'/app/Domains/*/Services/*.php',
        ];

        $files = [];
        foreach ($patterns as $pattern) {
            $files = array_merge($files, glob($pattern, GLOB_BRACE));
        }

        return array_values(array_filter($files, 'is_file'));
    }
}
