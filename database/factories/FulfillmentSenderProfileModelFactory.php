<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentSenderProfileModel>
 */
final class FulfillmentSenderProfileModelFactory extends Factory
{
    protected $model = FulfillmentSenderProfileModel::class;

    public function definition(): array
    {
        $company = $this->faker->company();

        return [
            'sender_code' => strtoupper($this->faker->unique()->bothify('SND-###')),
            'display_name' => $company.' '.$this->faker->citySuffix(),
            'company_name' => $company,
            'contact_person' => $this->faker->name(),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->e164PhoneNumber(),
            'street_name' => $this->faker->streetName(),
            'street_number' => $this->faker->buildingNumber(),
            'address_addition' => $this->faker->optional()->buildingNumber(),
            'postal_code' => $this->faker->postcode(),
            'city' => $this->faker->city(),
            'country_iso2' => strtoupper($this->faker->countryCode()),
        ];
    }
}
