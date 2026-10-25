<?php

namespace Database\Factories\Domains\Admin\Models;

use App\Domains\Admin\Models\SupportRequest;
use App\Domains\Admin\Models\SupportRequestStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    protected $model = SupportRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'seller',
            'category' => 'technical',
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'requester_name' => fake()->name(),
            'requester_contact' => fake()->safeEmail(),
            'status' => SupportRequestStatus::Open,
        ];
    }
}
