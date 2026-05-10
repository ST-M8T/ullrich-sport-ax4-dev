<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderRuleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FulfillmentSenderRuleModel>
 */
final class FulfillmentSenderRuleModelFactory extends Factory
{
    protected $model = FulfillmentSenderRuleModel::class;

    public function definition(): array
    {
        return [
            'priority' => $this->faker->numberBetween(1, 200),
            'rule_type' => $this->faker->randomElement(['zip', 'country', 'channel']),
            'match_value' => $this->faker->randomElement([
                (string) $this->faker->postcode(),
                strtoupper($this->faker->countryCode()),
                $this->faker->word(),
            ]),
            'target_sender_id' => FulfillmentSenderProfileModel::factory(),
            'is_active' => $this->faker->boolean(90),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
