<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Integrations;

use App\Application\Fulfillment\Integrations\Dhl\Services\DhlProductCatalogService;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class DhlProductCatalogServiceTest extends TestCase
{
    private DhlFreightGateway&MockInterface $gateway;

    private DhlProductCatalogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = Mockery::mock(DhlFreightGateway::class);
        $this->service = new DhlProductCatalogService($this->gateway);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // listProducts() tests

    public function test_list_products_returns_products_from_gateway(): void
    {
        $filters = ['country' => 'DE'];
        $products = [
            [
                'productId' => 'DFI',
                'name' => 'DHL Freight',
                'description' => 'National and international freight shipping',
            ],
            [
                'productId' => 'DFI_ECON',
                'name' => 'DHL Freight Economy',
                'description' => 'Cost-effective freight option',
            ],
        ];

        $this->gateway
            ->shouldReceive('listProducts')
            ->once()
            ->with($filters)
            ->andReturn($products);

        $result = $this->service->listProducts($filters);

        $this->assertCount(2, $result);
        $this->assertSame('DFI', $result[0]['productId']);
        $this->assertSame('DHL Freight', $result[0]['name']);
        $this->assertSame('National and international freight shipping', $result[0]['description']);
    }

    public function test_list_products_returns_empty_array_when_no_products(): void
    {
        $this->gateway
            ->shouldReceive('listProducts')
            ->once()
            ->with([])
            ->andReturn([]);

        $result = $this->service->listProducts([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_list_products_propagates_gateway_exception(): void
    {
        $this->gateway
            ->shouldReceive('listProducts')
            ->once()
            ->with([])
            ->andThrow(new \RuntimeException('Gateway connection failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gateway connection failed');

        $this->service->listProducts([]);
    }

    // listAdditionalServices() tests

    public function test_list_additional_services_returns_services_from_gateway(): void
    {
        $productId = 'DFI';
        $filters = ['country' => 'DE'];
        $services = [
            [
                'serviceCode' => 'SVC_001',
                'name' => 'Express Delivery',
                'description' => 'Next-day delivery option',
            ],
            [
                'serviceCode' => 'SVC_002',
                'name' => 'Saturday Delivery',
                'description' => 'Delivery on Saturday',
            ],
        ];

        $this->gateway
            ->shouldReceive('listAdditionalServices')
            ->once()
            ->with($productId, $filters)
            ->andReturn($services);

        $result = $this->service->listAdditionalServices($productId, $filters);

        $this->assertCount(2, $result);
        $this->assertSame('SVC_001', $result[0]['serviceCode']);
        $this->assertSame('Express Delivery', $result[0]['name']);
    }

    public function test_list_additional_services_returns_empty_array_when_no_services(): void
    {
        $this->gateway
            ->shouldReceive('listAdditionalServices')
            ->once()
            ->with('DFI', [])
            ->andReturn([]);

        $result = $this->service->listAdditionalServices('DFI', []);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_list_additional_services_propagates_gateway_exception(): void
    {
        $this->gateway
            ->shouldReceive('listAdditionalServices')
            ->once()
            ->with('DFI', [])
            ->andThrow(new \RuntimeException('Timeout'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timeout');

        $this->service->listAdditionalServices('DFI', []);
    }

    // validateAdditionalServices() tests

    public function test_validate_additional_services_returns_valid_result(): void
    {
        $productId = 'DFI';
        $services = ['SVC_001', 'SVC_002'];
        $response = [
            'valid' => true,
            'errors' => [],
        ];

        $this->gateway
            ->shouldReceive('validateAdditionalServices')
            ->once()
            ->with($productId, $services, [])
            ->andReturn($response);

        $result = $this->service->validateAdditionalServices($productId, $services, []);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_additional_services_returns_valid_result_with_is_valid_key(): void
    {
        $productId = 'DFI';
        $services = ['SVC_001'];
        $response = [
            'isValid' => true,
            'validationErrors' => [],
        ];

        $this->gateway
            ->shouldReceive('validateAdditionalServices')
            ->once()
            ->with($productId, $services, [])
            ->andReturn($response);

        $result = $this->service->validateAdditionalServices($productId, $services, []);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_additional_services_returns_invalid_result_for_invalid_combination(): void
    {
        $productId = 'DFI_ECON';
        $services = ['SVC_001'];
        $response = [
            'valid' => false,
            'errors' => ['Service SVC_001 is not available for product DFI_ECON'],
        ];

        $this->gateway
            ->shouldReceive('validateAdditionalServices')
            ->once()
            ->with($productId, $services, [])
            ->andReturn($response);

        $result = $this->service->validateAdditionalServices($productId, $services, []);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame('Service SVC_001 is not available for product DFI_ECON', $result['errors'][0]);
    }

    public function test_validate_additional_services_returns_invalid_result_on_gateway_exception(): void
    {
        $this->gateway
            ->shouldReceive('validateAdditionalServices')
            ->once()
            ->with('DFI', ['SVC_001'], [])
            ->andThrow(new \RuntimeException('Connection refused'));

        $result = $this->service->validateAdditionalServices('DFI', ['SVC_001'], []);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame('Validierung fehlgeschlagen', $result['errors'][0]);
    }
}