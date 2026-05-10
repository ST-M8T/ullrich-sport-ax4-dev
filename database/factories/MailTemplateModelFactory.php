<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Configuration\Eloquent\MailTemplateModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MailTemplateModel>
 */
final class MailTemplateModelFactory extends Factory
{
    protected $model = MailTemplateModel::class;

    public function definition(): array
    {
        return [
            'template_key' => Str::snake($this->faker->unique()->words(2, true)),
            'description' => $this->faker->sentence(4),
            'subject' => $this->faker->sentence(6),
            'body_html' => '<p>'.implode('</p><p>', $this->faker->paragraphs(2)).'</p>',
            'body_text' => implode("\n\n", $this->faker->paragraphs(2)),
            'is_active' => $this->faker->boolean(90),
            'updated_by_user_id' => $this->faker->optional()->numberBetween(1, 10),
        ];
    }
}
