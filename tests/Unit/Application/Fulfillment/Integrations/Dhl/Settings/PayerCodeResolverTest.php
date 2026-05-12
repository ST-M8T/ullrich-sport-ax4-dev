<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Integrations\Dhl\Settings;

use App\Application\Fulfillment\Integrations\Dhl\Settings\PayerCodeResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayerCodeResolverTest extends TestCase
{
    private PayerCodeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PayerCodeResolver;
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function whitelistProvider(): array
    {
        return [
            'DAP' => ['DAP', 'DAP'],
            'DDP' => ['DDP', 'DDP'],
            'EXW' => ['EXW', 'EXW'],
            'CIP' => ['CIP', 'CIP'],
            'lower-case' => ['dap', 'DAP'],
            'whitespace' => ['  ddp  ', 'DDP'],
        ];
    }

    #[DataProvider('whitelistProvider')]
    public function test_validates_allowed_codes(string $input, string $expected): void
    {
        self::assertSame($expected, $this->resolver->validate($input));
    }

    public function test_empty_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->validate('');
    }

    public function test_unknown_code_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->validate('FOO');
    }

    public function test_allowed_returns_whitelist(): void
    {
        self::assertSame(['DAP', 'DDP', 'EXW', 'CIP'], $this->resolver->allowed());
    }
}
