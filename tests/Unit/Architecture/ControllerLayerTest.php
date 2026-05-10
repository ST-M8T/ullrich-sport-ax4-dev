<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class ControllerLayerTest extends TestCase
{
    public function test_controllers_do_not_import_repositories_models_or_db_facade(): void
    {
        foreach ($this->controllerFiles() as $file) {
            $content = file_get_contents($file);
            $this->assertIsString($content);

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Domain\\\\.*Repository[^;]*;/m',
                $content,
                $file.' darf keine Repository-Imports enthalten.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+App\\\\Models\\\\/m',
                $content,
                $file.' darf keine Model-Imports enthalten.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^use\\s+Illuminate\\\\Support\\\\Facades\\\\DB;/m',
                $content,
                $file.' darf keine DB-Facade im Controller importieren.'
            );
        }
    }

    /**
     * @return list<string>
     */
    private function controllerFiles(): array
    {
        $pattern = dirname(__DIR__, 3).'/app/Http/Controllers/**/*.php';
        $files = glob($pattern, GLOB_BRACE);

        return array_values(array_filter($files, 'is_file'));
    }
}
