<?php

namespace Tests\Support;

use PHPUnit\Framework\Assert;

trait MatchesHtmlSnapshot
{
    protected function assertMatchesHtmlSnapshot(string $snapshotName, string $actualHtml): void
    {
        $snapshotDirectory = base_path('tests/__snapshots__');
        if (! is_dir($snapshotDirectory)) {
            mkdir($snapshotDirectory, 0777, true);
        }

        $normalized = $this->normalizeHtml($actualHtml);
        $snapshotPath = $snapshotDirectory.'/'.$snapshotName.'.snap.html';

        if (! file_exists($snapshotPath)) {
            file_put_contents($snapshotPath, $normalized);
            Assert::fail("Snapshot {$snapshotName} was missing and has been created. Confirm the contents and rerun the tests.");
        }

        $expectedRaw = file_get_contents($snapshotPath);
        $expected = $this->normalizeHtml($expectedRaw ?? '');

        if ($expectedRaw !== $expected) {
            file_put_contents($snapshotPath, $expected);
        }

        Assert::assertSame($expected, $normalized, "Snapshot mismatch for {$snapshotName}");
    }

    protected function normalizeHtml(string $html): string
    {
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s+/', ' ', $html);

        return trim($html);
    }
}
