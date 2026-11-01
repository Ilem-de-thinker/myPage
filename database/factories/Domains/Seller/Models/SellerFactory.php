<?php

namespace Database\Factories\Domains\Seller\Models;

use App\Domains\Admin\Enums\AdminRole;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<Seller> */
class SellerFactory extends Factory
{
    protected $model = Seller::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+234'.fake()->numerify('80########'),
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    /**
     * The platform team moderates the platform, so an admin is a seller with a
     * flag rather than a separate account type.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'is_admin' => true,
            'admin_role' => AdminRole::CoreAdmin,
        ]);
    }

    public function coreAdmin(): static
    {
        return $this->admin();
    }

    public function staffAdmin(): static
    {
        return $this->state(fn () => [
            'is_admin' => true,
            'admin_role' => AdminRole::StaffAdmin,
        ]);
    }
}
