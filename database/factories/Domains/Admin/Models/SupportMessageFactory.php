<?php

namespace Database\Factories\Domains\Admin\Models;

use App\Domains\Admin\Models\SupportMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportMessage>
 */
class SupportMessageFactory extends Factory
{
    protected $model = SupportMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_type' => 'admin',
            'body' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }
}
