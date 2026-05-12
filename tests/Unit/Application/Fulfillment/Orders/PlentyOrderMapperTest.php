<?php

namespace Tests\Unit\Application\Fulfillment\Orders;

use App\Application\Fulfillment\Orders\PlentyOrderMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PlentyOrderMapperTest extends TestCase
{
    private PlentyOrderMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new PlentyOrderMapper;
    }

    public function test_extract_customer_number_from_billing_address_contact_id(): void
    {
        $payload = [
            'id' => 1,
            'billingAddress' => ['contactId' => 'C-12345'],
        ];

        // 'C-12345' ist nicht numerisch -> null gemaess defensiver Regel.
        $this->assertNull($this->mapper->extractCustomerNumber($payload));
    }

    public function test_extract_customer_number_returns_string_for_numeric_contact_id(): void
    {
        $payload = [
            'id' => 1,
            'billingAddress' => ['contactId' => 4567],
        ];

        $result = $this->mapper->extractCustomerNumber($payload);

        $this->assertIsString($result);
        $this->assertSame('4567', $result);
    }

    public function test_extract_customer_number_returns_null_when_billing_address_missing(): void
    {
        $payload = ['id' => 1];

        $this->assertNull($this->mapper->extractCustomerNumber($payload));
    }

    public function test_extract_customer_number_returns_null_when_contact_id_missing_in_billing_address(): void
    {
        $payload = [
            'id' => 1,
            'billingAddress' => ['name' => 'Anyone'],
        ];

        $this->assertNull($this->mapper->extractCustomerNumber($payload));
    }

    public function test_extract_customer_number_returns_null_when_contact_id_empty_string(): void
    {
        $payload = [
            'id' => 1,
            'billingAddress' => ['contactId' => '   '],
        ];

        $this->assertNull($this->mapper->extractCustomerNumber($payload));
    }

    public function test_extract_customer_number_truncates_to_dhl_reference_max_length(): void
    {
        // Numerischer String, laenger als 35 Zeichen -> truncate.
        $longNumeric = str_repeat('1', 50);
        $payload = [
            'id' => 1,
            'billingAddress' => ['contactId' => $longNumeric],
        ];

        $result = $this->mapper->extractCustomerNumber($payload);

        $this->assertNotNull($result);
        $this->assertSame(PlentyOrderMapper::CUSTOMER_NUMBER_MAX_LENGTH, strlen($result));
        $this->assertSame(str_repeat('1', PlentyOrderMapper::CUSTOMER_NUMBER_MAX_LENGTH), $result);
    }

    public function test_extract_external_order_id_returns_int(): void
    {
        $payload = ['id' => '789'];

        $this->assertSame(789, $this->mapper->extractExternalOrderId($payload));
    }

    public function test_extract_external_order_id_throws_when_id_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->extractExternalOrderId([]);
    }

    public function test_extract_external_order_id_throws_when_id_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->extractExternalOrderId(['id' => '']);
    }

    public function test_extract_external_order_id_throws_when_id_not_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mapper->extractExternalOrderId(['id' => 0]);
    }

    public function test_map_to_order_data_provides_int_customer_number_for_entity_hydration(): void
    {
        $payload = [
            'id' => 10,
            'billingAddress' => ['contactId' => 4711],
            'status' => 'BOOKED',
        ];

        $mapped = $this->mapper->mapToOrderData($payload);

        $this->assertSame('4711', $mapped['customerNumber']);
        $this->assertSame(4711, $mapped['customerNumberAsInt']);
        $this->assertSame(10, $mapped['externalOrderId']);
        $this->assertTrue($mapped['isBooked']);
    }
}
